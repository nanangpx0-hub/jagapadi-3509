<#
.SYNOPSIS
    Jalankan JAGAPADI Mobile di perangkat fisik Android

.DESCRIPTION
    Khusus untuk perangkat fisik yang terhubung via USB.
    IP LAN mesin ini: 192.168.10.5 (dari ipconfig)

.NOTES
    Prasyarat perangkat fisik:
    1. USB Debugging aktif: Pengaturan → Opsi Pengembang → USB Debugging
    2. Terhubung ke Wi-Fi yang SAMA dengan komputer ini
    3. Kabel USB tersambung ke komputer
#>

$FLUTTER        = "C:\flutter\bin\flutter.bat"
$ADB            = "C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools\adb.exe"
$SERVER_IP_LAN  = "192.168.10.5"   # IP komputer ini dari ipconfig
$SERVER_PORT    = "8080"

# Set PATH
$env:PATH             = "C:\flutter\bin;C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools;$env:PATH"
$env:ANDROID_HOME     = "C:\Users\IPDS\AppData\Local\Android\Sdk"
$env:ANDROID_SDK_ROOT = "C:\Users\IPDS\AppData\Local\Android\Sdk"

Write-Host ""
Write-Host "=== JAGAPADI Mobile — Perangkat Fisik ===" -ForegroundColor Cyan
Write-Host "Server IP: $SERVER_IP_LAN`:$SERVER_PORT" -ForegroundColor Gray

# ── Cek perangkat ────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "-- Mengecek perangkat USB --" -ForegroundColor Yellow
$devices = & $ADB devices 2>&1
$deviceLines = ($devices -join "`n") -split "`n" | Where-Object { $_ -match "\sdevice$" }

if ($deviceLines.Count -eq 0) {
    Write-Host "TIDAK ADA PERANGKAT FISIK TERDETEKSI." -ForegroundColor Red
    Write-Host ""
    Write-Host "Checklist:" -ForegroundColor Yellow
    Write-Host "  [ ] Kabel USB tersambung" -ForegroundColor White
    Write-Host "  [ ] USB Debugging aktif di perangkat" -ForegroundColor White
    Write-Host "  [ ] Pilih 'Transfer file' (MTP) di perangkat saat USB tersambung" -ForegroundColor White
    Write-Host "  [ ] Izinkan debug di pop-up 'Izinkan USB Debugging?' di perangkat" -ForegroundColor White
    Write-Host ""
    Write-Host "Setelah perangkat terdeteksi, cek dengan: adb devices" -ForegroundColor Yellow
    exit 1
}

Write-Host "  Perangkat fisik terdeteksi:" -ForegroundColor Green
$deviceLines | ForEach-Object { Write-Host "    $_" -ForegroundColor White }

# ── Coba adb reverse dulu (lebih direkomendasikan) ────────────────────────────
Write-Host ""
Write-Host "-- Mencoba adb reverse (metode direkomendasikan) --" -ForegroundColor Yellow
Write-Host "  adb reverse tcp:$SERVER_PORT tcp:$SERVER_PORT" -ForegroundColor Gray

& $ADB reverse "tcp:$SERVER_PORT" "tcp:$SERVER_PORT" 2>&1
$adbReverseOk = ($LASTEXITCODE -eq 0)

if ($adbReverseOk) {
    # Dengan adb reverse, perangkat fisik bisa pakai 10.0.2.2 juga
    $API_URL = "http://10.0.2.2:${SERVER_PORT}/api/v1"
    Write-Host "  [OK] adb reverse berhasil!" -ForegroundColor Green
    Write-Host "  Perangkat fisik akan menggunakan 10.0.2.2:$SERVER_PORT" -ForegroundColor Green
    Write-Host "  (Perangkat tidak perlu di Wi-Fi yang sama dengan komputer)" -ForegroundColor Gray
} else {
    # Fallback ke IP LAN
    $API_URL = "http://${SERVER_IP_LAN}:${SERVER_PORT}/api/v1"
    Write-Host "  [INFO] adb reverse gagal. Menggunakan IP LAN: $SERVER_IP_LAN" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  PENTING: Pastikan perangkat Android di Wi-Fi yang SAMA dengan komputer ini!" -ForegroundColor Yellow
    Write-Host "  IP komputer ini: $SERVER_IP_LAN" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  Untuk cek dari perangkat Android, buka browser dan akses:" -ForegroundColor Gray
    Write-Host "  http://$SERVER_IP_LAN`:$SERVER_PORT/api/v1/health" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "-- API URL yang digunakan --" -ForegroundColor Yellow
Write-Host "  $API_URL" -ForegroundColor Cyan

# ── Jalankan ─────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "-- Menjalankan flutter run --" -ForegroundColor Yellow
Set-Location "C:\laragon\www\jagapadi-3509\mobile"

& $FLUTTER run "--dart-define=API_BASE_URL=$API_URL"
