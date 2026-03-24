# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-03-24

### Added
- Initial release
- Multi-model wind forecast fetching (ECMWF, AROME, GFS27, ICONGLOBAL, HRRR)
- Consensus calculation with circular mean for wind direction
- UDP output to Loxone Miniserver
- Preferred model support (default values without model prefix)
- PHP web UI with settings and live data view
- LoxBerry V3 compatible plugin structure
- GitHub Actions CI pipeline (lint, test, package)
- Automated release pipeline with installable ZIP

[0.1.0]: https://github.com/amyc-codes/loxberry-plugin-windyapp/releases/tag/v0.1.0
