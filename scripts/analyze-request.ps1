# Script to analyze the last request performance
# Run this AFTER making a request in the browser

Write-Host "=== Analyzing Last Request ===" -ForegroundColor Cyan
Write-Host ""

Write-Host "Last 5 requests with timing:" -ForegroundColor Yellow
docker-compose exec nginx tail -5 /var/log/nginx/access.log | ForEach-Object {
    if ($_ -match 'rt=([\d.]+)') {
        $rt = [double]$matches[1]
        $color = if ($rt -lt 1) { "Green" } elseif ($rt -lt 3) { "Yellow" } else { "Red" }
        Write-Host $_ -ForegroundColor $color
    } else {
        Write-Host $_
    }
}

Write-Host ""
Write-Host "Extracting timing information:" -ForegroundColor Yellow
docker-compose exec nginx tail -1 /var/log/nginx/access.log | ForEach-Object {
    if ($_ -match 'rt=([\d.]+).*uct="([\d.-]+)".*uht="([\d.-]+)".*urt="([\d.-]+)"') {
        $rt = [double]$matches[1]
        $uct = $matches[2]
        $uht = $matches[3]
        $urt = $matches[4]
        
        Write-Host "  Total Request Time: $rt seconds" -ForegroundColor $(if ($rt -lt 1) { "Green" } elseif ($rt -lt 3) { "Yellow" } else { "Red" })
        Write-Host "  Upstream Connect Time: $uct seconds"
        Write-Host "  Upstream Header Time: $uht seconds"
        Write-Host "  Upstream Response Time: $urt seconds"
        
        if ($rt -gt 3) {
            Write-Host ""
            Write-Host "  ⚠️  SLOW REQUEST DETECTED!" -ForegroundColor Red
            if ($uct -gt 1) {
                Write-Host "     - Connection to PHP-FPM is slow" -ForegroundColor Yellow
            }
            if ($uht -gt 1) {
                Write-Host "     - PHP-FPM is taking time to start processing" -ForegroundColor Yellow
            }
            if ($urt -gt 1) {
                Write-Host "     - PHP execution is slow" -ForegroundColor Yellow
            }
        }
    }
}

Write-Host ""
Write-Host "PHP-FPM status:" -ForegroundColor Yellow
docker-compose exec app php-fpm-healthcheck 2>&1 || docker-compose exec app ps aux | grep php-fpm | head -3
