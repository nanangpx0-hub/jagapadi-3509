<#
.SYNOPSIS
Launch all services and run Playwright E2E tests for JAGAPADI.
Usage:
  .\run-tests.ps1                    # Run all tests
  .\run-tests.ps1 -TestFilter "auth" # Single file
  .\run-tests.ps1 -SkipServer        # If PHP server already running
#>

param(
    [string]$TestFilter = "",
    [int]$RemotePort = 9222,
    [string]$ChromePath = "C:\Program Files\Google\Chrome\Application\chrome.exe",
    [switch]$SkipServer = $false
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Path (Split-Path -Parent $MyInvocation.MyCommand.Path) -Parent
$PhpExe = "C:\laragon\bin\php\php-8.2.32-nts-Win32-vs16-x64\php.exe"
$NpxCmd = "C:\laragon\bin\nodejs\node-v18\npx.cmd"
$PlaywrightBin = "C:\laragon\www\jagapadi-3509\node_modules\.bin\playwright.cmd"

# ── Start PHP server ──
if (-not $SkipServer) {
    Write-Host "Starting PHP server on localhost:8080..." -ForegroundColor Cyan
    $global:phpProc = Start-Process -FilePath $PhpExe -ArgumentList "-S localhost:8080 -t `"$ProjectRoot\backend\public`"" -PassThru -NoNewWindow
    Start-Sleep -Seconds 3

    try {
        $test = Invoke-WebRequest -Uri "http://localhost:8080/login" -TimeoutSec 5 -UseBasicParsing
        Write-Host "  [OK] PHP server is running" -ForegroundColor Green
    } catch {
        Write-Host "  [FAIL] PHP server did not start" -ForegroundColor Red
        exit 1
    }
}

# ── Detect test runner ──
if (Test-Path $PlaywrightBin) {
    $Runner = $PlaywrightBin
} else {
    $Runner = $NpxCmd
}

# ── Run tests ──
Set-Location (Split-Path -Parent $MyInvocation.MyCommand.Path)
Write-Host "Running Playwright tests..." -ForegroundColor Cyan

if ($TestFilter) {
    & $Runner test "$TestFilter"
} else {
    & $Runner test
}

$exitCode = $LASTEXITCODE

# ── Cleanup ──
if (-not $SkipServer -and $global:phpProc) {
    Write-Host "Stopping PHP server..." -ForegroundColor Cyan
    Stop-Process -Id $global:phpProc.Id -Force -ErrorAction SilentlyContinue
}

Write-Host "Done. Exit code: $exitCode" -ForegroundColor Cyan
exit $exitCode
