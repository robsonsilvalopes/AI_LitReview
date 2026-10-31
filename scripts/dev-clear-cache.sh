#!/bin/bash
# Script to clear all Laravel caches for development
# This ensures changes are reflected immediately

echo "Clearing Laravel caches..."

docker-compose exec app php artisan optimize:clear
docker-compose exec app php artisan view:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear

echo "Cache cleared! Changes should now be reflected."
