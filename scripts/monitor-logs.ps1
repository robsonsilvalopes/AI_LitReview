# Script to monitor logs in real-time
# Usage: .\scripts\monitor-logs.ps1

Write-Host "Monitoring Nginx and PHP-FPM logs..." -ForegroundColor Cyan
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host ""

# Monitor Nginx access log
Start-Job -ScriptBlock {
    docker-compose logs -f --tail=10 nginx
} | Out-Null

# Monitor PHP-FPM
Start-Job -ScriptBlock {
    docker-compose logs -f --tail=10 app
} | Out-Null

# Monitor Laravel logs
Start-Job -ScriptBlock {
    docker-compose exec app tail -f storage/logs/laravel.log 2>&1
} | Out-Null

Write-Host "Logs are being monitored. Check the jobs with: Get-Job" -ForegroundColor Green
Write-Host "Stop monitoring with: Stop-Job *; Remove-Job *" -ForegroundColor Yellow
