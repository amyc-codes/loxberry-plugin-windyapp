# Contributing

Thanks for your interest in contributing to the Windy.app LoxBerry Plugin!

## Development Setup

1. Clone the repo
2. Install Python 3.9+ and `requests` (`pip install requests`)
3. Install PHP CLI for web frontend development

## Plugin Structure

```
├── bin/
│   ├── fetch.py          # Main fetcher — talks to windy.app API, sends UDP
│   └── cronjob.sh        # LoxBerry cron wrapper
├── config/
│   └── windyapp.cfg      # Default configuration
├── daemon/
│   └── daemon            # LoxBerry daemon placeholder
├── icons/
│   ├── icon.svg          # Source icon
│   ├── icon_64.png       # 64x64 plugin icon
│   └── icon_256.png      # 256x256 plugin icon
├── webfrontend/
│   └── htmlauth/
│       └── index.php     # Web config UI
├── uninstall/
│   └── uninstall         # Cleanup script
├── plugin.cfg            # LoxBerry plugin metadata
├── postinstall.sh        # Post-install setup
├── preupgrade.sh         # Pre-upgrade backup
└── release.cfg           # Auto-update config
```

## Testing Locally

```bash
# Set up fake LoxBerry paths
export LBHOMEDIR=/tmp/loxberry
mkdir -p $LBHOMEDIR/config/plugins/windyapp
mkdir -p $LBHOMEDIR/data/plugins/windyapp
mkdir -p $LBHOMEDIR/log/plugins/windyapp
cp config/windyapp.cfg $LBHOMEDIR/config/plugins/windyapp/

# Run the fetcher (without UDP send)
python3 bin/fetch.py
```

## Releasing

1. Update `VERSION` in `plugin.cfg`
2. Commit and push
3. Tag: `git tag v0.2.0 && git push --tags`
4. GitHub Actions will create the release with the installable ZIP

## Code Style

- Python: PEP 8
- PHP: PSR-12 where practical (LoxBerry templates are jQuery Mobile, so pragmatism > purity)
- Shell: `set -euo pipefail` where appropriate
