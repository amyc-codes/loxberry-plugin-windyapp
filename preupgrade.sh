#!/bin/bash
# Pre-upgrade script - backup config
if [ -f "$LBPCONFIGDIR/windyapp.cfg" ]; then
    cp "$LBPCONFIGDIR/windyapp.cfg" "/tmp/windyapp_cfg_backup"
    echo "<OK> Config backed up."
fi
