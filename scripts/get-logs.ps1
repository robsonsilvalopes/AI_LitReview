# Script to get logs quickly (using docker-compose logs, not file reads)
# This is MUCH faster than reading files in WSL volumes

param(
    [string]$Service = "all",
    [int]$Lines = 50
)

Write-Host "Getting logs for: $Service (last $Lines lines)" -ForegroundColor Cyan
Write-Host ""

if ($Service -eq "all") {
    docker-compose logs --tail=$Lines
} else {
    docker-compose logs --tail=$Lines $Service
}

Write-Host ""
Write-Host "Tip: Use 'docker-compose logs -f' to follow logs in real-time" -ForegroundColor Yellow
