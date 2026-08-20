<#
.SYNOPSIS
    Setup PATH untuk development JAGAPADI Mobile (Flutter + Android SDK)

.DESCRIPTION
    Script ini melakukan dua hal:
    1. Menambahkan Flutter dan Android platform-tools ke PATH sesi SAAT INI
    2. Menambahkan ke PATH PERMANEN (User-level) agar tidak perlu run ulang tiap buka terminal

.NOTES
    Jalankan sekali dengan: .\setup-dev-env.ps1
    Setelah itu buka terminal baru — flutter dan adb langsung bisa dipanggil.
#>

# ── Path yang ditemukan di mesin ini ─────────────────────────────────────────
$FLUTTER_BIN   = "C:\flutter\bin"
$ANDROID_TOOLS = "C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools"
$ANDROID_SDK   = "C:\Users\IPDS\AppData\Local\Android\Sdk"

Write-Host ""
Write-Host "=== JAGAPADI Mobile — Setup Development Environment ===" -ForegroundColor Cyan
Write-Host ""

# ── Verifikasi path ada ──────────────────────────────────────────────────────
$errors = 0
foreach ($p in @($FLUTTER_BIN, $ANDROID_TOOLS)) {
    if (-not (Test-Path $p)) {
        Write-Host "  [TIDAK DITEMUKAN] $p" -ForegroundColor Red
        $errors++
    } else {
        Write-Host "  [OK] $p" -ForegroundColor Green
    }
}

if ($errors -gt 0) {
    Write-Host ""
    Write-Host "GAGAL: Beberapa path tidak ditemukan. Edit variabel di atas sesuai instalasi Anda." -ForegroundColor Red
    exit 1
}

# ── 1. Tambah ke PATH sesi saat ini (langsung aktif) ─────────────────────────
Write-Host ""
Write-Host "1. Mengaktifkan PATH untuk sesi terminal ini..." -ForegroundColor Yellow

$currentPath = $env:PATH -split ";"
$toAdd = @($FLUTTER_BIN, $ANDROID_TOOLS)

foreach ($p in $toAdd) {
    if ($currentPath -notcontains $p) {
        $env:PATH = "$p;$env:PATH"
        Write-Host "   + Ditambahkan ke PATH sesi: $p" -ForegroundColor Green
    } else {
        Write-Host "   (sudah ada) $p" -ForegroundColor Gray
    }
}

# Set ANDROID_HOME agar Flutter bisa menemukan SDK
$env:ANDROID_HOME = $ANDROID_SDK
$env:ANDROID_SDK_ROOT = $ANDROID_SDK
Write-Host "   + ANDROID_HOME = $ANDROID_SDK" -ForegroundColor Green

# ── 2. Tambah ke PATH permanen (User environment) ────────────────────────────
Write-Host ""
Write-Host "2. Menambahkan ke PATH permanen (User-level)..." -ForegroundColor Yellow

$userPath = [System.Environment]::GetEnvironmentVariable("PATH", "User") -split ";"
$changed = $false

foreach ($p in $toAdd) {
    if ($userPath -notcontains $p) {
        $userPath = @($p) + $userPath
        Write-Host "   + Ditambahkan permanent: $p" -ForegroundColor Green
        $changed = $true
    } else {
        Write-Host "   (sudah ada permanent) $p" -ForegroundColor Gray
    }
}

if ($changed) {
    [System.Environment]::SetEnvironmentVariable("PATH", ($userPath -join ";"), "User")
    # Set ANDROID_HOME permanen
    [System.Environment]::SetEnvironmentVariable("ANDROID_HOME", $ANDROID_SDK, "User")
    [System.Environment]::SetEnvironmentVariable("ANDROID_SDK_ROOT", $ANDROID_SDK, "User")
    Write-Host "   PATH permanen berhasil diperbarui." -ForegroundColor Green
}

# ── 3. Verifikasi ─────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "3. Verifikasi tools..." -ForegroundColor Yellow

$flutterExe = Join-Path $FLUTTER_BIN "flutter.bat"
$adbExe     = Join-Path $ANDROID_TOOLS "adb.exe"

if (Test-Path $flutterExe) {
    Write-Host "   [OK] flutter.bat ditemukan: $flutterExe" -ForegroundColor Green
} else {
    Write-Host "   [GAGAL] flutter.bat tidak ada di $flutterExe" -ForegroundColor Red
}

if (Test-Path $adbExe) {
    Write-Host "   [OK] adb.exe ditemukan: $adbExe" -ForegroundColor Green
} else {
    Write-Host "   [GAGAL] adb.exe tidak ada di $adbExe" -ForegroundColor Red
}

# ── Ringkasan ─────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "=== SELESAI ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Untuk sesi terminal INI, jalankan langsung:" -ForegroundColor White
Write-Host "  flutter --version" -ForegroundColor Yellow
Write-Host "  adb version" -ForegroundColor Yellow
Write-Host ""
Write-Host "Untuk terminal BARU (setelah PATH permanen tersimpan):" -ForegroundColor White
Write-Host "  Tutup dan buka ulang PowerShell/CMD" -ForegroundColor Yellow
Write-Host ""
