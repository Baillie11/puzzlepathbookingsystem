#!/bin/bash
# WP-CLI command to drop pp_hunts table
# Run this from your WordPress root directory

wp db query "DROP TABLE IF EXISTS wp2s_pp_hunts;"

echo "pp_hunts table dropped successfully!"
