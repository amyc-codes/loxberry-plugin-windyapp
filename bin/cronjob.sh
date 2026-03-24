#!/bin/bash
# Windy.app Wind Forecast Cronjob for LoxBerry

PLUGINNAME=REPLACELBPPLUGINDIR
PATH="/sbin:/bin:/usr/sbin:/usr/bin:$LBHOMEDIR/bin:$LBHOMEDIR/sbin"

if [ -f /etc/environment ]; then
    ENVIRONMENT=$(cat /etc/environment)
    export $ENVIRONMENT
fi

# Source LoxBerry environment
if [ -f "$LBHOMEDIR/libs/bashlib/loxberry_system.sh" ]; then
    . "$LBHOMEDIR/libs/bashlib/loxberry_system.sh"
fi

python3 "$LBHOMEDIR/bin/plugins/$PLUGINNAME/fetch.py"
