# Script to clear all Laravel caches for development (PowerShell)
# This ensures changes are reflected immediately

Write-Host "Clearing Laravel caches..." -ForegroundColor Cyan

docker-compose exec app php artisan optimize:clear
docker-compose exec app php artisan view:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear

Write-Host "Cache cleared! Changes should now be reflected." -ForegroundColor Green
