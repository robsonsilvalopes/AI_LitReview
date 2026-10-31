# Quick log access - much faster than reading files

Write-Host "=== Quick Logs ===" -ForegroundColor Cyan
Write-Host ""

Write-Host "Nginx (last 10 lines):" -ForegroundColor Yellow
docker-compose logs --tail=10 nginx

Write-Host ""
Write-Host "PHP-FPM (last 10 lines):" -ForegroundColor Yellow
docker-compose logs --tail=10 app

Write-Host ""
Write-Host "To follow logs in real-time:" -ForegroundColor Green
Write-Host "  docker-compose logs -f nginx app" -ForegroundColor White
