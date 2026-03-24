# Windy.app Wind Forecast — LoxBerry Plugin

Multi-model wind forecasts from [windy.app](https://windy.app) for your Loxone Miniserver.

Built for wing foiling, kitesurfing, and anyone who wants detailed wind data from multiple weather models in their smart home.

## Features

- **Multi-model forecasts**: ECMWF, AROME, GFS, ICON, HRRR and more
- **Consensus calculation**: Circular-mean averaged wind from all models
- **Miniserver integration**: Sends wind speed (m/s, knots, km/h) and direction via UDP
- **Preferred model**: One model's data sent as the default (no prefix) for easy Loxone programming
- **Web UI**: Configure models, location, and see current data in the LoxBerry admin
- **Flexible scheduling**: 5 to 60 minute fetch intervals

## Installation

1. Download the latest release ZIP from [GitHub Releases](https://github.com/amyc-codes/loxberry-plugin-windyapp/releases)
2. In LoxBerry → Plugin Management, paste the ZIP URL and enter your SecurePIN
3. Configure in the plugin settings (location, models, UDP port)

## Miniserver Setup

Create a **Virtual UDP Input** in Loxone Config:

- **UDP Port**: 7001 (or whatever you set in the plugin)
- **UDP Command Recognition**: `\i`

Then create **Virtual UDP Input Commands** for the values you need:

| Command Name | Command Recognition |
|---|---|
| Wind Speed (m/s) | `windyapp_wind_speed=\v` |
| Wind Speed (knots) | `windyapp_wind_speed_kn=\v` |
| Wind Speed (km/h) | `windyapp_wind_speed_kmh=\v` |
| Wind Direction (°) | `windyapp_wind_dir=\v` |
| Consensus Speed | `windyapp_consensus_speed_kn=\v` |

For per-model data, use the model name prefix:
- `windyapp_ecmwf_wind_speed=\v`
- `windyapp_arome_wind_speed_kn=\v`
- etc.

## Configuration

All settings are in the web UI under the plugin's Settings tab:

- **Location**: Latitude/Longitude of your wind spot
- **Models**: Choose which weather models to fetch (ECMWF and AROME recommended for Europe)
- **Preferred Model**: This model's data is sent without a model prefix for easy use
- **Fetch Interval**: How often to pull new forecasts (15 min default)
- **UDP Settings**: Port and enable/disable

## Data Source

Wind data comes from [windy.app](https://windy.app)'s public widget API. No API key required.

## License

MIT

## Author

Markus Cozowicz (m@cozowicz.at)
