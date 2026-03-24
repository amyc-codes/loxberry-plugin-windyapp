<div align="center">

<img src="icons/icon.svg" width="128" height="128" alt="Windy.app Plugin Icon">

# Windy.app Wind Forecast

**Multi-model wind forecasts for your Loxone Miniserver**

[![CI](https://github.com/amyc-codes/loxberry-plugin-windyapp/actions/workflows/ci.yml/badge.svg)](https://github.com/amyc-codes/loxberry-plugin-windyapp/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/amyc-codes/loxberry-plugin-windyapp?label=release&color=blue)](https://github.com/amyc-codes/loxberry-plugin-windyapp/releases/latest)
[![LoxBerry](https://img.shields.io/badge/LoxBerry-V3-green?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNiAxNiI+PGNpcmNsZSBjeD0iOCIgY3k9IjgiIHI9IjgiIGZpbGw9IiM1QThFMUMiLz48L3N2Zz4=)](https://loxberry.de)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Python 3.9+](https://img.shields.io/badge/python-3.9+-3776ab?logo=python&logoColor=white)](https://www.python.org)

*Built for wing foiling, kitesurfing, and anyone who wants serious wind data in their smart home.*

---

[Installation](#-installation) · [Configuration](#%EF%B8%8F-configuration) · [Miniserver Setup](#-miniserver-setup) · [Models](#-weather-models) · [Contributing](CONTRIBUTING.md)

</div>

## ✨ Features

| Feature | Description |
|---|---|
| 🌊 **Multi-model forecasts** | ECMWF, AROME, GFS, ICON, HRRR — compare models side by side |
| 📊 **Consensus calculation** | Circular-mean averaged wind speed & direction across all models |
| 📡 **Miniserver integration** | Wind speed (m/s, kn, km/h) and direction via UDP |
| ⭐ **Preferred model** | One model's data sent as default for easy Loxone programming |
| 🖥️ **Web UI** | Configure models, location, and view live data in LoxBerry admin |
| ⏱️ **Flexible scheduling** | 5–60 minute fetch intervals via LoxBerry cron |
| 🔑 **No API key needed** | Uses windy.app's public widget API |

## 📦 Installation

### From GitHub Release (recommended)

1. Go to [**Releases**](https://github.com/amyc-codes/loxberry-plugin-windyapp/releases/latest)
2. Copy the `.zip` URL
3. In LoxBerry → **Plugin Management**, paste the URL and enter your SecurePIN

### From Source

```
https://github.com/amyc-codes/loxberry-plugin-windyapp/archive/main.zip
```

## ⚙️ Configuration

After installation, open the plugin in LoxBerry:

<table>
<tr>
<td width="200"><b>📍 Location</b></td>
<td>Latitude & longitude of your wind spot (decimal degrees)</td>
</tr>
<tr>
<td><b>🌬️ Models</b></td>
<td>Select which weather models to fetch (see <a href="#-weather-models">models</a> below)</td>
</tr>
<tr>
<td><b>⭐ Preferred Model</b></td>
<td>This model's data is sent without a prefix — easy to use in Loxone</td>
</tr>
<tr>
<td><b>⏱️ Interval</b></td>
<td>Fetch frequency: 5, 10, 15, 30, or 60 minutes</td>
</tr>
<tr>
<td><b>📡 UDP</b></td>
<td>Enable/disable and set the port for Miniserver communication</td>
</tr>
</table>

## 📡 Miniserver Setup

Create a **Virtual UDP Input** in Loxone Config:

| Setting | Value |
|---|---|
| **UDP Port** | `7001` (or your configured port) |
| **Command Recognition** | `\i` |

Then add **Virtual UDP Input Commands**:

### Default Values (preferred model)

```
windyapp_wind_speed=\v          → m/s
windyapp_wind_speed_kn=\v       → knots
windyapp_wind_speed_kmh=\v      → km/h
windyapp_wind_dir=\v            → degrees (0-360)
```

### Consensus (average of all models)

```
windyapp_consensus_speed=\v     → m/s
windyapp_consensus_speed_kn=\v  → knots
windyapp_consensus_dir=\v       → degrees
```

### Per-Model Values

Replace `{model}` with lowercase model name (`ecmwf`, `arome`, `gfs27`, etc.):

```
windyapp_{model}_wind_speed=\v     → m/s
windyapp_{model}_wind_speed_kn=\v  → knots
windyapp_{model}_wind_dir=\v       → degrees
```

<details>
<summary><b>💡 Example: ECMWF + AROME comparison</b></summary>

```
windyapp_ecmwf_wind_speed_kn=\v
windyapp_ecmwf_wind_dir=\v
windyapp_arome_wind_speed_kn=\v
windyapp_arome_wind_dir=\v
```

Use these in a Loxone visualization to show both models side by side — great for deciding whether conditions are reliable enough for a session!

</details>

## 🌍 Weather Models

| Model | Coverage | Resolution | Best For |
|---|---|---|---|
| **ECMWF** | Global | ~9 km | ⭐ Best overall accuracy, Europe |
| **AROME** | Europe | ~1.3 km | ⭐ Best detail for Alps/Central Europe |
| **GFS27** | Global | ~27 km | Broad patterns, long-range |
| **ICONGLOBAL** | Global | ~13 km | Good for Central Europe |
| **HRRR** | North America | ~3 km | Best for US spots |
| **NAM** | North America | ~12 km | US/Canada forecasts |
| **MFWAM** | Global (ocean) | — | Wave/swell data |
| **MYOCEAN** | Global (ocean) | — | Marine currents |

> **Recommendation for Central Europe:** Enable **ECMWF** (preferred) + **AROME** + **ICONGLOBAL**. AROME has the highest resolution for Alpine wind patterns.

## 🏗️ Architecture

```
┌─────────────┐     HTTP/JSON     ┌──────────────┐
│  windy.app  │ ◄──────────────── │   LoxBerry   │
│  Widget API │                   │  (fetch.py)  │
└─────────────┘                   └──────┬───────┘
                                         │ UDP
                                         ▼
                                  ┌──────────────┐
                                  │   Loxone     │
                                  │  Miniserver  │
                                  └──────────────┘
```

The fetcher runs on a configurable cron schedule:

1. **Fetch** — Pulls forecast JSON from windy.app for each enabled model
2. **Parse** — Extracts current-hour wind speed & direction, plus 24h hourly forecast
3. **Consensus** — Computes circular-mean average across all models
4. **Send** — Pushes values to Miniserver via UDP
5. **Store** — Saves `current.json` for the web UI

## 🔧 Manual Fetch

SSH into your LoxBerry and run:

```bash
python3 /opt/loxberry/bin/plugins/windyapp/fetch.py
```

Or use the **"Fetch Now"** button in the Wind Data tab of the web UI.

## 📋 Compatibility

- **LoxBerry** 3.0+
- **Python** 3.9+ (ships with LoxBerry 3.x)
- **Loxone Miniserver** Gen 1 / Gen 2

## 📄 License

[MIT](LICENSE) — Markus Cozowicz

## 🙏 Credits

- Wind data: [windy.app](https://windy.app)
- Plugin framework: [LoxBerry](https://loxberry.de)
