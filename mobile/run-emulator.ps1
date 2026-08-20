<#
.SYNOPSIS
    Jalankan JAGAPADI Mobile di emulator Android (AVD)

.DESCRIPTION
    Script ini:
    1. Set PATH sementara untuk flutter dan adb
    2. Verifikasi emulator/perangkat terhubung
    3. Jalankan flutter run dengan URL server yang benar
    4. Tampilkan instruksi jika ada masalah

.PARAMETER Mode
    'debug'   = mode debug (default), hot reload aktif
    'release' = mode release, lebih dekat ke APK produksi

.EXAMPLE
    .\run-emulator.ps1
    .\run-emulator.ps1 -Mode release
#>
param(
    [ValidateSet("debug", "release")]
    [string]$Mode = "debug"
)

# ── Konfigurasi ───────────────────────────────────────────────────────────────
$FLUTTER   = "C:\flutter\bin\flutter.bat"
$ADB       = "C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools\adb.exe"

# IP server berdasarkan jenis perangkat:
#   Emulator AVD    → 10.0.2.2 (alias ke localhost mesin host)
#   Perangkat fisik → IP LAN mesin host (dari ipconfig)
$SERVER_HOST_EMULATOR = "10.0.2.2"
$SERVER_HOST_PHYSICAL = "192.168.10.5"
$SERVER_PORT          = "80"

# URL tanpa port (Laragon Apache default port 80)
$BASE_PATH = "/jagapadi-3509/api/v1"

# ── Set PATH sementara ────────────────────────────────────────────────────────
$env:PATH = "C:\flutter\bin;C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools;$env:PATH"
$env:ANDROID_HOME     = "C:\Users\IPDS\AppData\Local\Android\Sdk"
$env:ANDROID_SDK_ROOT = "C:\Users\IPDS\AppData\Local\Android\Sdk"

Write-Host ""
Write-Host "=== JAGAPADI Mobile Runner ===" -ForegroundColor Cyan
Write-Host "Mode: $Mode" -ForegroundColor Gray

# ── Verifikasi tools ──────────────────────────────────────────────────────────
Write-Host ""
Write-Host "-- Mengecek tools --" -ForegroundColor Yellow

foreach ($tool in @($FLUTTER, $ADB)) {
    if (-not (Test-Path $tool)) {
        Write-Host "GAGAL: $tool tidak ditemukan." -ForegroundColor Red
        Write-Host "Jalankan setup-dev-env.ps1 terlebih dahulu." -ForegroundColor Yellow
        exit 1
    }
    Write-Host "  [OK] $tool" -ForegroundColor Green
}

# ── Deteksi perangkat yang terhubung ─────────────────────────────────────────
Write-Host ""
Write-Host "-- Mendeteksi perangkat Android --" -ForegroundColor Yellow

$adbOutput = & $ADB devices 2>&1
$lines = ($adbOutput -join "`n") -split "`n" | Where-Object { $_ -match "\s(device|emulator)" }

if ($lines.Count -eq 0) {
    Write-Host ""
    Write-Host "TIDAK ADA PERANGKAT TERDETEKSI." -ForegroundColor Red
    Write-Host ""
    Write-Host "Langkah yang bisa dilakukan:" -ForegroundColor Yellow
    Write-Host "  1. Buka Android Studio → Device Manager → Start AVD" -ForegroundColor White
    Write-Host "  2. Atau sambungkan perangkat fisik via USB dengan USB Debugging aktif" -ForegroundColor White
    Write-Host "  3. Untuk perangkat fisik: Pengaturan → Opsi Pengembang → USB Debugging = ON" -ForegroundColor White
    Write-Host ""
    Write-Host "Setelah perangkat terhubung, jalankan script ini lagi." -ForegroundColor Yellow
    exit 1
}

Write-Host "  Perangkat terdeteksi:" -ForegroundColor Green
$lines | ForEach-Object { Write-Host "    $_" -ForegroundColor White }

# Deteksi apakah menggunakan emulator atau perangkat fisik
$isEmulator = ($lines -join "") -match "emulator"
if ($isEmulator) {
    $TARGET_IP = $SERVER_HOST_EMULATOR
    Write-Host "  → Emulator AVD terdeteksi. Menggunakan 10.0.2.2" -ForegroundColor Cyan
} else {
    $TARGET_IP = $SERVER_HOST_PHYSICAL
    Write-Host "  → Perangkat fisik terdeteksi. Menggunakan $SERVER_HOST_PHYSICAL" -ForegroundColor Cyan

    # Cek adb reverse untuk perangkat fisik (alternatif — bisa skip)
    Write-Host ""
    Write-Host "  (Opsional) Menyiapkan adb reverse agar bisa pakai 10.0.2.2..." -ForegroundColor Gray
    & $ADB reverse tcp:$SERVER_PORT tcp:$SERVER_PORT 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        $TARGET_IP = $SERVER_HOST_EMULATOR
        Write-Host "  → adb reverse berhasil. Perangkat fisik juga pakai 10.0.2.2" -ForegroundColor Green
    } else {
        Write-Host "  → adb reverse gagal. Akan menggunakan IP LAN: $TARGET_IP" -ForegroundColor Yellow
    }
}

$API_URL = "http://${TARGET_IP}:${SERVER_PORT}/api/v1"
Write-Host ""
Write-Host "-- Konfigurasi --" -ForegroundColor Yellow
Write-Host "  API_BASE_URL = $API_URL" -ForegroundColor Cyan

# ── Verifikasi server aktif (HTTP test dari host) ─────────────────────────────
Write-Host ""
Write-Host "-- Verifikasi server Laragon --" -ForegroundColor Yellow
try {
    $healthUrl  = "http://localhost/jagapadi-3509/api/v1/health"
    $response   = Invoke-WebRequest -Uri $healthUrl -TimeoutSec 5 -ErrorAction Stop
    $content    = $response.Content
    Write-Host "  [OK] Server merespons HTTP $($response.StatusCode)" -ForegroundColor Green
    if ($content -match "JAGAPADI") {
        Write-Host "  [OK] Backend JAGAPADI terdeteksi." -ForegroundColor Green
    } else {
        Write-Host "  [PERINGATAN] Server merespons tapi bukan API JAGAPADI." -ForegroundColor Yellow
        Write-Host "  Pastikan document root = backend/public" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  [GAGAL] Server tidak merespons di http://localhost:$SERVER_PORT" -ForegroundColor Red
    Write-Host ""
    Write-Host "  Kemungkinan penyebab:" -ForegroundColor Yellow
    Write-Host "    1. Laragon belum dijalankan → Buka Laragon, klik 'Start All'" -ForegroundColor White
    Write-Host "    2. Apache/Nginx di Laragon tidak berjalan di port $SERVER_PORT" -ForegroundColor White
    Write-Host "    3. Virtual host belum dikonfigurasi untuk jagapadi-3509" -ForegroundColor White
    Write-Host ""
    Write-Host "  Coba akses manual: http://localhost:$SERVER_PORT/api/v1/health" -ForegroundColor Yellow
    Write-Host ""

    $confirm = Read-Host "Lanjutkan menjalankan Flutter meskipun server tidak terdeteksi? (y/N)"
    if ($confirm -ne "y" -and $confirm -ne "Y") {
        Write-Host "Dibatalkan." -ForegroundColor Gray
        exit 1
    }
}

# ── Jalankan Flutter ──────────────────────────────────────────────────────────
Write-Host ""
Write-Host "-- Menjalankan flutter run ($Mode) --" -ForegroundColor Yellow
Write-Host "  URL: $API_URL" -ForegroundColor Gray
Write-Host "  Tekan 'r' untuk hot reload, 'q' untuk keluar" -ForegroundColor Gray
Write-Host ""

Set-Location "C:\laragon\www\jagapadi-3509\mobile"

if ($Mode -eq "release") {
    & $FLUTTER run --release `
        "--dart-define=API_BASE_URL=$API_URL"
} else {
    & $FLUTTER run `
        "--dart-define=API_BASE_URL=$API_URL"
}
