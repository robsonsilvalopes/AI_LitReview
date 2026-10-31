#!/bin/bash
set -e

# Create storage directories if they don't exist
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/testing
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Fix permissions for storage and bootstrap/cache
# Using proper permissions for Linux/WSL
chown -R www-data:www-data /var/www/html/storage || true
chmod -R 775 /var/www/html/storage || true

chown -R www-data:www-data /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/bootstrap/cache || true

# Execute the original command (PHP-FPM will handle user switching)
exec $@