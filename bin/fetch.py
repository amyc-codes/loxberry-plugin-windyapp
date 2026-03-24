#!/usr/bin/env python3
"""
Windy.app Wind Forecast Fetcher for LoxBerry
Fetches multi-model wind data and sends to Miniserver via UDP.
"""

import configparser
import json
import logging
import os
import socket
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

try:
    import requests
except ImportError:
    print("ERROR: requests module not found. Run: pip3 install requests", file=sys.stderr)
    sys.exit(1)

# LoxBerry paths from environment
LBHOMEDIR = os.environ.get('LBHOMEDIR', '/opt/loxberry')
LBPCONFIGDIR = os.environ.get('LBPCONFIGDIR', f'{LBHOMEDIR}/config/plugins/windyapp')
LBPDATADIR = os.environ.get('LBPDATADIR', f'{LBHOMEDIR}/data/plugins/windyapp')
LBPLOGDIR = os.environ.get('LBPLOGDIR', f'{LBHOMEDIR}/log/plugins/windyapp')
LBPSYSDIR = os.environ.get('LBPSYSDIR', f'{LBHOMEDIR}/system')

CONFIG_FILE = f'{LBPCONFIGDIR}/windyapp.cfg'
DATA_FILE = f'{LBPDATADIR}/current.json'
WIDGET_URL = 'https://windy.app/widget3/coord/{model}/{lat}/{lon}/1'

# Ensure directories exist
os.makedirs(LBPDATADIR, exist_ok=True)
os.makedirs(LBPLOGDIR, exist_ok=True)

# Logging
log_file = f'{LBPLOGDIR}/windyapp.log'
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s %(levelname)s %(message)s',
    handlers=[
        logging.FileHandler(log_file, mode='a'),
        logging.StreamHandler(sys.stdout),
    ]
)
log = logging.getLogger('windyapp')


def read_config():
    """Read plugin configuration."""
    cfg = configparser.ConfigParser()
    cfg.read(CONFIG_FILE)

    lat = cfg.get('WINDYAPP', 'LAT', fallback='47.85397')
    lon = cfg.get('WINDYAPP', 'LON', fallback='13.0266')
    models_str = cfg.get('WINDYAPP', 'MODELS', fallback='ECMWF;AROME;GFS27;ICONGLOBAL;HRRR')
    models_str = models_str.strip('"').strip("'")
    models = [m.strip() for m in models_str.split(';') if m.strip()]
    preferred = cfg.get('WINDYAPP', 'PREFERRED_MODEL', fallback='ECMWF')

    send_udp = cfg.getint('SERVER', 'SENDUDP', fallback=1)
    udp_port = cfg.getint('SERVER', 'UDPPORT', fallback=7001)
    ms_no = cfg.getint('SERVER', 'MSNO', fallback=1)

    return {
        'lat': float(lat),
        'lon': float(lon),
        'models': models,
        'preferred_model': preferred,
        'send_udp': bool(send_udp),
        'udp_port': udp_port,
        'ms_no': ms_no,
    }


def get_miniserver_ip(ms_no=1):
    """Read Miniserver IP from LoxBerry general.cfg."""
    general_cfg = f'{LBHOMEDIR}/config/system/general.cfg'
    cfg = configparser.ConfigParser()
    cfg.read(general_cfg)
    section = f'MINISERVER{ms_no}'
    ip = cfg.get(section, 'IPADDRESS', fallback=None)
    if not ip:
        # Try plugindatabase approach
        section_alt = 'MINISERVER1'
        ip = cfg.get(section_alt, 'IPADDRESS', fallback=None)
    return ip


def get_miniserver_ip_from_dat(ms_no=1):
    """Fallback: parse plugindatabase.dat or general.json."""
    general_json = f'{LBHOMEDIR}/config/system/general.json'
    if os.path.exists(general_json):
        with open(general_json) as f:
            data = json.load(f)
        ms = data.get('Miniserver', {})
        for key, val in ms.items():
            if val.get('Msno') == ms_no or key == str(ms_no):
                return val.get('Ipaddress')
    return None


def fetch_model_data(lat, lon, model):
    """Fetch wind forecast from windy.app for a single model."""
    url = WIDGET_URL.format(model=model, lat=lat, lon=lon)
    try:
        resp = requests.get(url, timeout=30)
        resp.raise_for_status()
        data = resp.json()
    except requests.RequestException as e:
        log.error(f'Failed to fetch {model}: {e}')
        return None

    spot_info = data.get('spotInfo', {})
    forecast_json_str = spot_info.get('forecastJson')
    if not forecast_json_str:
        log.warning(f'{model}: No forecastJson in response')
        return None

    try:
        forecast = json.loads(forecast_json_str)
    except json.JSONDecodeError:
        log.error(f'{model}: Invalid forecastJson')
        return None

    now_ts = int(time.time())
    model_update = data.get('modelUpdateTime')

    # Find the forecast entry closest to now
    current = None
    next_hours = []
    for entry in forecast:
        ts = int(entry.get('timestamp', 0))
        if ts >= now_ts and current is None:
            current = entry
        if ts >= now_ts and len(next_hours) < 24:
            next_hours.append(entry)

    if not current and forecast:
        current = forecast[-1]  # Use last available if all in the past

    if not current:
        log.warning(f'{model}: No current forecast data')
        return None

    return {
        'model': model,
        'model_update_time': model_update,
        'fetched_at': datetime.now(timezone.utc).isoformat(),
        'current': {
            'timestamp': current.get('timestamp'),
            'wind_speed': current.get('windSpeed'),
            'wind_direction': current.get('windDirection'),
            'spot_date': current.get('spotDate'),
            'spot_hour': current.get('spotHour'),
        },
        'hourly': [
            {
                'timestamp': e.get('timestamp'),
                'wind_speed': e.get('windSpeed'),
                'wind_direction': e.get('windDirection'),
                'spot_date': e.get('spotDate'),
                'spot_hour': e.get('spotHour'),
            }
            for e in next_hours
        ],
        'forecast_count': len(forecast),
    }


def send_udp_data(ip, port, values):
    """Send values to Miniserver via UDP."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        for key, val in values.items():
            if val is None:
                continue
            msg = f'{key}={val}'
            sock.sendto(msg.encode('utf-8'), (ip, port))
        log.info(f'Sent {len(values)} values via UDP to {ip}:{port}')
    except Exception as e:
        log.error(f'UDP send failed: {e}')
    finally:
        sock.close()


def direction_name(deg):
    """Convert degrees to compass direction name."""
    if deg is None:
        return 'N/A'
    dirs = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
            'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW']
    idx = round(deg / 22.5) % 16
    return dirs[idx]


def ms_to_kn(ms):
    """Convert m/s to knots."""
    if ms is None:
        return None
    return round(ms * 1.94384, 1)


def ms_to_kmh(ms):
    """Convert m/s to km/h."""
    if ms is None:
        return None
    return round(ms * 3.6, 1)


def main():
    log.info('=' * 60)
    log.info('Windy.app Wind Forecast - fetch started')

    config = read_config()
    log.info(f'Location: {config["lat"]}, {config["lon"]}')
    log.info(f'Models: {", ".join(config["models"])}')
    log.info(f'Preferred: {config["preferred_model"]}')

    # Fetch all models
    results = {}
    for model in config['models']:
        log.info(f'Fetching {model}...')
        data = fetch_model_data(config['lat'], config['lon'], model)
        if data:
            ws = data['current']['wind_speed']
            wd = data['current']['wind_direction']
            log.info(f'  {model}: {ws} m/s ({ms_to_kn(ws)} kn) from {direction_name(wd)} ({wd}°)')
            results[model] = data
        else:
            log.warning(f'  {model}: FAILED')

    if not results:
        log.error('No model data fetched successfully')
        sys.exit(1)

    # Compute consensus (average of all models for current hour)
    speeds = [r['current']['wind_speed'] for r in results.values() if r['current']['wind_speed'] is not None]
    dirs = [r['current']['wind_direction'] for r in results.values() if r['current']['wind_direction'] is not None]

    consensus_speed = round(sum(speeds) / len(speeds), 2) if speeds else None
    # Average direction using circular mean
    import math
    if dirs:
        sin_sum = sum(math.sin(math.radians(d)) for d in dirs)
        cos_sum = sum(math.cos(math.radians(d)) for d in dirs)
        consensus_dir = round(math.degrees(math.atan2(sin_sum, cos_sum)) % 360, 1)
    else:
        consensus_dir = None

    # Build output data
    output = {
        'fetched_at': datetime.now(timezone.utc).isoformat(),
        'location': {'lat': config['lat'], 'lon': config['lon']},
        'preferred_model': config['preferred_model'],
        'consensus': {
            'wind_speed_ms': consensus_speed,
            'wind_speed_kn': ms_to_kn(consensus_speed),
            'wind_speed_kmh': ms_to_kmh(consensus_speed),
            'wind_direction_deg': consensus_dir,
            'wind_direction_name': direction_name(consensus_dir),
            'models_count': len(results),
        },
        'models': {},
    }

    for model, data in results.items():
        c = data['current']
        output['models'][model] = {
            'wind_speed_ms': c['wind_speed'],
            'wind_speed_kn': ms_to_kn(c['wind_speed']),
            'wind_speed_kmh': ms_to_kmh(c['wind_speed']),
            'wind_direction_deg': c['wind_direction'],
            'wind_direction_name': direction_name(c['wind_direction']),
            'model_update_time': data['model_update_time'],
            'spot_date': c['spot_date'],
            'spot_hour': c['spot_hour'],
            'hourly': data['hourly'],
        }

    # Write data file
    with open(DATA_FILE, 'w') as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    log.info(f'Data written to {DATA_FILE}')

    # Send to Miniserver via UDP
    if config['send_udp']:
        ms_ip = get_miniserver_ip(config['ms_no'])
        if not ms_ip:
            ms_ip = get_miniserver_ip_from_dat(config['ms_no'])
        if not ms_ip:
            log.error('Could not determine Miniserver IP')
        else:
            udp_values = {}

            # Per-model values
            for model, data in results.items():
                c = data['current']
                prefix = f'windyapp_{model.lower()}'
                udp_values[f'{prefix}_wind_speed'] = c['wind_speed']
                udp_values[f'{prefix}_wind_speed_kn'] = ms_to_kn(c['wind_speed'])
                udp_values[f'{prefix}_wind_dir'] = c['wind_direction']

            # Preferred model as default (no model prefix)
            pref = config['preferred_model']
            if pref in results:
                c = results[pref]['current']
                udp_values['windyapp_wind_speed'] = c['wind_speed']
                udp_values['windyapp_wind_speed_kn'] = ms_to_kn(c['wind_speed'])
                udp_values['windyapp_wind_speed_kmh'] = ms_to_kmh(c['wind_speed'])
                udp_values['windyapp_wind_dir'] = c['wind_direction']

            # Consensus
            udp_values['windyapp_consensus_speed'] = consensus_speed
            udp_values['windyapp_consensus_speed_kn'] = ms_to_kn(consensus_speed)
            udp_values['windyapp_consensus_dir'] = consensus_dir

            send_udp_data(ms_ip, config['udp_port'], udp_values)

    log.info('Fetch completed successfully')
    log.info('=' * 60)


if __name__ == '__main__':
    main()
