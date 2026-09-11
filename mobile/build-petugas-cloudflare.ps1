<#
.SYNOPSIS
    Build APK JAGAPADI khusus PETUGAS via Cloudflare Tunnel

.DESCRIPTION
    APK Petugas langsung konek ke https://jagapadi.my.id/api/v1
    Server asal tetap localhost:8080 yang di-expose via Cloudflare Tunnel.
    Dashboard web: https://jagapadi.my.id/dashboard (sama DB, role petugas)

.PARAMETER BuildType
    debug   = untuk testing di device fisik (default)
    release = untuk distribusi (butuh key.properties)

.EXAMPLE
    .\build-petugas-cloudflare.ps1                  # debug
    .\build-petugas-cloudflare.ps1 -BuildType release
#>
param(
    [ValidateSet("debug","release")]
    [string]$BuildType = "debug"
)

$FLUTTER   = "C:\flutter\bin\flutter.bat"
$API_URL   = "https://jagapadi.my.id/api/v1"
$WEB_URL   = "https://jagapadi.my.id/dashboard"

$env:PATH             = "C:\flutter\bin;C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools;$env:PATH"
$env:ANDROID_HOME     = "C:\Users\IPDS\AppData\Local\Android\Sdk"
$env:ANDROID_SDK_ROOT = "C:\Users\IPDS\AppData\Local\Android\Sdk"

Write-Host ""
Write-Host "=== JAGAPADI PETUGAS — Cloudflare Build ===" -ForegroundColor Cyan
Write-Host "API      : $API_URL" -ForegroundColor Green
Write-Host "Dashboard: $WEB_URL" -ForegroundColor Green
Write-Host "Role     : petugas (login petugas01 / email petugas)" -ForegroundColor Yellow
Write-Host "Tunnel   : cloudflared tunnel --url http://localhost:8080" -ForegroundColor Gray
Write-Host "Build    : $BuildType" -ForegroundColor Gray

# Preflight: cek backend health via localhost (sebelum tunnel)
Write-Host "`n[1/3] Cek backend health (localhost:8080)..." -ForegroundColor Yellow
try {
    $r = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 5 -ErrorAction Stop
    Write-Host "  OK: $($r | ConvertTo-Json -Compress)" -ForegroundColor Green
} catch {
    Write-Host "  WARN: localhost:8080/api/v1/health tidak respons — pastikan 'php -S localhost:8080 -t backend/public' jalan" -ForegroundColor Yellow
    Write-Host "  Lanjut build tetap, tapi test login akan gagal jika backend mati." -ForegroundColor Gray
}

if ($BuildType -eq "release") {
    Write-Host "`nPERHATIAN: Build RELEASE Petugas" -ForegroundColor Yellow
    Write-Host "  Pastikan android/app/key.properties sudah benar" -ForegroundColor Yellow
    $ok = Read-Host "Lanjutkan? (y/N)"
    if ($ok -ne "y" -and $ok -ne "Y") { exit 0 }
}

Set-Location "C:\laragon\www\jagapadi-3509\mobile"

Write-Host "`n[2/3] flutter pub get..." -ForegroundColor Yellow
& $FLUTTER pub get
if ($LASTEXITCODE -ne 0) { Write-Host "GAGAL pub get" -ForegroundColor Red; exit 1 }

Write-Host "`n[3/3] Building APK Petugas ($BuildType) -> $API_URL" -ForegroundColor Yellow
if ($BuildType -eq "release") {
    & $FLUTTER build apk --release --split-per-abi --dart-define=API_BASE_URL=$API_URL
} else {
    & $FLUTTER build apk --debug --dart-define=API_BASE_URL=$API_URL
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n=== BUILD PETUGAS BERHASIL ===" -ForegroundColor Green
    Get-ChildItem "build\app\outputs\flutter-apk\*.apk" | Select-Object Name, @{N='SizeMB';E={[math]::Round($_.Length/1MB,2)}}, LastWriteTime | Format-Table -AutoSize
    Write-Host ""
    Write-Host "Install ke device:" -ForegroundColor Cyan
    Write-Host "  adb install -r build\app\outputs\flutter-apk\app-arm64-v8a-$BuildType.apk"
    Write-Host ""
    Write-Host "Login Petugas (contoh):" -ForegroundColor Cyan
    Write-Host "  petugas01 / Jember3509  (atau email petugas / Jagapadi1! sesuai daftar-user.md)"
    Write-Host "  Jika via Cloudflare, pastikan tunnel aktif lalu test:" -ForegroundColor Gray
    Write-Host "  curl https://jagapadi.my.id/api/v1/health"
} else {
    Write-Host "`n=== BUILD GAGAL ===" -ForegroundColor Red
    exit 1
}
