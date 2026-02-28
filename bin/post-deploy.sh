#!/bin/sh
# Runs on WP Engine after deploy. Install Composer deps so vendor/ exists.
set -e
echo "Running post-deploy: composer install..."
composer install --no-dev --optimize-autoloader --no-interaction
echo "Post-deploy complete."
