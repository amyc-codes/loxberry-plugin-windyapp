#!/bin/bash
# Post-install script for Windy.app Wind Forecast plugin

echo "<INFO> Installing Python dependencies..."
pip3 install requests 2>/dev/null || pip install requests 2>/dev/null || true
echo "<OK> Python dependencies installed."

# Ensure data and log directories exist
mkdir -p "$LBPDATADIR" 2>/dev/null || true
mkdir -p "$LBPLOGDIR" 2>/dev/null || true

# Set up cron (default: every 15 minutes)
if [ -f "$LBPCONFIGDIR/windyapp.cfg" ]; then
    CRON=$(grep -i "^CRON=" "$LBPCONFIGDIR/windyapp.cfg" | cut -d= -f2 | tr -d '"' | tr -d ' ')
    if [ -z "$CRON" ]; then
        CRON=15
    fi
fi

# Create cron symlink
CRONDIR="cron.${CRON}min"
if [ -d "$LBHOMEDIR/system/cron/$CRONDIR" ]; then
    ln -sf "$LBHOMEDIR/bin/plugins/$LBPPLUGINDIR/cronjob.sh" "$LBHOMEDIR/system/cron/$CRONDIR/windyapp"
    echo "<OK> Cron job installed (every ${CRON} minutes)."
else
    # Fall back to 15 min
    ln -sf "$LBHOMEDIR/bin/plugins/$LBPPLUGINDIR/cronjob.sh" "$LBHOMEDIR/system/cron/cron.15min/windyapp"
    echo "<OK> Cron job installed (every 15 minutes, fallback)."
fi

echo "<OK> Windy.app Wind Forecast plugin installed successfully."
