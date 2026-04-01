$ErrorActionPreference = "Stop"

try {
  $dockerInfo = & docker info --format "{{.ServerVersion}}" 2>$null
} catch {
  $dockerInfo = $null
}

if (-not $dockerInfo -or $LASTEXITCODE -ne 0) {
  Write-Host ""
  Write-Host "Docker Desktop is not running." -ForegroundColor Yellow
  Write-Host "1. Open Docker Desktop" -ForegroundColor Cyan
  Write-Host "2. Wait until it says Engine running" -ForegroundColor Cyan
  Write-Host "3. Run this script again" -ForegroundColor Cyan
  exit 1
}

Write-Host "Docker is running. Starting DocFlow Studio..." -ForegroundColor Green
docker compose up --build
