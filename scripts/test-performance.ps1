# Script to test performance and monitor logs
# Run this, then make a request in the browser

Write-Host "=== Performance Test ===" -ForegroundColor Cyan
Write-Host "1. Testing PHP-FPM response time..." -ForegroundColor Yellow

$startTime = Get-Date
docker-compose exec app php -r "echo 'PHP Response Time Test: '; echo microtime(true);" | Out-Null
$endTime = Get-Date
$phpTime = ($endTime - $startTime).TotalMilliseconds
Write-Host "   PHP execution time: $phpTime ms" -ForegroundColor $(if ($phpTime -lt 100) { "Green" } else { "Red" })

Write-Host ""
Write-Host "2. Testing database connection..." -ForegroundColor Yellow
$startTime = Get-Date
docker-compose exec app php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>&1 | Out-Null
$endTime = Get-Date
$dbTime = ($endTime - $startTime).TotalMilliseconds
Write-Host "   Database connection time: $dbTime ms" -ForegroundColor $(if ($dbTime -lt 1000) { "Green" } else { "Red" })

Write-Host ""
Write-Host "3. Testing menu cache..." -ForegroundColor Yellow
$startTime = Get-Date
docker-compose exec app php artisan tinker --execute="Cache::get('menu_data'); echo 'OK';" 2>&1 | Out-Null
$endTime = Get-Date
$cacheTime = ($endTime - $startTime).TotalMilliseconds
Write-Host "   Cache access time: $cacheTime ms" -ForegroundColor $(if ($cacheTime -lt 50) { "Green" } else { "Red" })

Write-Host ""
Write-Host "4. Checking Nginx access log format..." -ForegroundColor Yellow
docker-compose exec nginx cat /var/log/nginx/access.log | Select-Object -Last 1

Write-Host ""
Write-Host "=== Next Steps ===" -ForegroundColor Cyan
Write-Host "Now make a request in your browser to http://localhost:8000/projects/2" -ForegroundColor Yellow
Write-Host "Then run: docker-compose logs --tail=20 nginx app" -ForegroundColor Yellow
