<#
.SYNOPSIS
    Build APK JAGAPADI untuk distribusi

.DESCRIPTION
    Build APK debug atau release dengan URL server yang dikonfigurasi.

.PARAMETER Target
    'emulator'  = URL untuk emulator AVD (10.0.2.2:8080)
    'lan'       = URL untuk perangkat fisik via LAN (192.168.10.5:8080)
    'prod'      = URL produksi (wajib isi $PROD_URL di bawah)

.PARAMETER BuildType
    'debug'   = APK debug (default)
    'release' = APK release (perlu keystore di key.properties)

.EXAMPLE
    .\build-apk.ps1 -Target lan -BuildType debug
    .\build-apk.ps1 -Target prod -BuildType release
#>
param(
    [ValidateSet("emulator", "lan", "prod")]
    [string]$Target = "lan",

    [ValidateSet("debug", "release")]
    [string]$BuildType = "debug"
)

$FLUTTER   = "C:\flutter\bin\flutter.bat"
$PROD_URL  = "https://jagapadi.example.go.id/api/v1"   # ← Ganti dengan URL produksi

# Set PATH
$env:PATH             = "C:\flutter\bin;C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools;$env:PATH"
$env:ANDROID_HOME     = "C:\Users\IPDS\AppData\Local\Android\Sdk"
$env:ANDROID_SDK_ROOT = "C:\Users\IPDS\AppData\Local\Android\Sdk"

$API_URL = switch ($Target) {
    "emulator" { "http://10.0.2.2:8080/api/v1" }
    "lan"      { "http://192.168.10.5:8080/api/v1" }
    "prod"     { $PROD_URL }
}

Write-Host ""
Write-Host "=== JAGAPADI APK Builder ===" -ForegroundColor Cyan
Write-Host "Target  : $Target" -ForegroundColor Gray
Write-Host "Type    : $BuildType" -ForegroundColor Gray
Write-Host "API URL : $API_URL" -ForegroundColor Cyan

if ($Target -eq "prod" -and $BuildType -eq "release") {
    Write-Host ""
    Write-Host "PERHATIAN: Build RELEASE untuk PRODUKSI" -ForegroundColor Yellow
    Write-Host "  Pastikan key.properties sudah dikonfigurasi!" -ForegroundColor Yellow
    $ok = Read-Host "Lanjutkan? (y/N)"
    if ($ok -ne "y" -and $ok -ne "Y") { exit 0 }
}

Set-Location "C:\laragon\www\jagapadi-3509\mobile"

Write-Host ""
Write-Host "-- flutter pub get --" -ForegroundColor Yellow
& $FLUTTER pub get

Write-Host ""
Write-Host "-- Building APK ($BuildType) --" -ForegroundColor Yellow

if ($BuildType -eq "release") {
    & $FLUTTER build apk --release `
        --split-per-abi `
        "--dart-define=API_BASE_URL=$API_URL"
} else {
    & $FLUTTER build apk --debug `
        "--dart-define=API_BASE_URL=$API_URL"
}

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "=== BUILD BERHASIL ===" -ForegroundColor Green
    Write-Host "APK tersedia di:" -ForegroundColor White
    Get-ChildItem "build\app\outputs\flutter-apk\*.apk" -ErrorAction SilentlyContinue |
        Select-Object Name, @{N='Size (KB)'; E={[math]::Round($_.Length/1KB)}} |
        Format-Table -AutoSize
} else {
    Write-Host ""
    Write-Host "=== BUILD GAGAL ===" -ForegroundColor Red
    Write-Host "Cek output di atas untuk detail error." -ForegroundColor Yellow
    exit 1
}
