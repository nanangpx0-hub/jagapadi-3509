<#
.SYNOPSIS
    Skrip sinkronisasi media uploads JAGAPADI dari cPanel hosting ke Laragon Windows (Pull Model).

.DESCRIPTION
    Menjalankan rclone untuk mendownload/mensinkronisasi aset gambar dan dokumen (public/uploads)
    dari shared hosting cPanel (SFTP / FTPS) ke instalasi lokal Laragon secara aman, efisien,
    dan idempotent (hanya mendownload file baru atau yang berubah).

.PARAMETER ConfigPath
    Path ke file konfigurasi rclone (default: config/rclone.conf).

.PARAMETER RemoteName
    Nama profil remote di rclone.conf (default: cpanel_sftp).

.PARAMETER RemotePath
    Path direktori uploads di remote cPanel (default: /home/jagapadi/public_html/public/uploads).

.PARAMETER LocalPath
    Path direktori tujuan penyimpanan lokal (default: public/uploads).

.PARAMETER DryRun
    Simulasi sinkronisasi tanpa mendownload/mengubah file apapun di disk lokal.

.PARAMETER BandwidthLimit
    Batas penggunaan bandwidth download, misal: 10M, 2M, 500k.

.PARAMETER SyncMode
    Mode operasi rclone: 'copy' (default, aman) atau 'sync' (hapus file lokal yang tidak ada di remote).

.PARAMETER LogLevel
    Level logging rclone: 'DEBUG', 'INFO', 'NOTICE', 'ERROR' (default: INFO).

.PARAMETER LogFile
    Path file log hasil sinkronisasi (default: storage/logs/rclone_media_sync.log).

.PARAMETER RcloneExe
    Path binary rclone.exe kustom.

.EXAMPLE
    .\scripts\sync_media_rclone.ps1 -DryRun
    .\scripts\sync_media_rclone.ps1 -BandwidthLimit 5M -SyncMode copy
    .\scripts\sync_media_rclone.ps1 -RemoteName cpanel_ftp
#>

[CmdletBinding()]
param(
    [string]$ConfigPath,
    [string]$RemoteName,
    [string]$RemotePath,
    [string]$LocalPath,
    [switch]$DryRun,
    [string]$BandwidthLimit,
    [ValidateSet('copy', 'sync')]
    [string]$SyncMode,
    [ValidateSet('DEBUG', 'INFO', 'NOTICE', 'ERROR')]
    [string]$LogLevel,
    [string]$LogFile,
    [string]$RcloneExe
)

$ErrorActionPreference = 'Stop'

# 1. Resolusi Path Dasar Proyek
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = (Split-Path -Parent $ScriptDir).Replace('\', '/')

# 2. Muat variabel dari .env.backup jika ada
$envMap = @{}
$envCandidates = @(
    (Join-Path $ScriptDir ".env.backup"),
    (Join-Path $ProjectRoot ".env.backup")
)

foreach ($envFile in $envCandidates) {
    if (Test-Path $envFile) {
        Get-Content $envFile | ForEach-Object {
            $line = $_.Trim()
            if ($line -and -not $line.StartsWith('#') -and $line.Contains('=')) {
                $idx = $line.IndexOf('=')
                $key = $line.Substring(0, $idx).Trim()
                $val = $line.Substring($idx + 1).Trim()
                if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
                    $val = $val.Substring(1, $val.Length - 2)
                }
                if (-not [string]::IsNullOrWhiteSpace($key)) {
                    $envMap[$key] = $val
                }
            }
        }
        break
    }
}

# 3. Tentukan Nilai Parameter Final (Prioritas: CLI parameter > .env.backup > Default)
if (-not $RcloneExe -and $envMap.ContainsKey('RCLONE_EXE_PATH')) {
    $RcloneExe = $envMap['RCLONE_EXE_PATH']
}

if (-not $ConfigPath) {
    $ConfigPath = if ($envMap.ContainsKey('RCLONE_CONFIG_PATH')) { $envMap['RCLONE_CONFIG_PATH'] } else { "config/rclone.conf" }
}

if (-not $RemoteName) {
    $RemoteName = if ($envMap.ContainsKey('REMOTE_NAME')) { $envMap['REMOTE_NAME'] } else { "cpanel_sftp" }
}

if (-not $RemotePath) {
    $RemotePath = if ($envMap.ContainsKey('REMOTE_UPLOADS_PATH')) { $envMap['REMOTE_UPLOADS_PATH'] } else { "/home/jagapadi/public_html/public/uploads" }
}

if (-not $LocalPath) {
    $LocalPath = if ($envMap.ContainsKey('LOCAL_UPLOADS_PATH')) { $envMap['LOCAL_UPLOADS_PATH'] } else { "public/uploads" }
}

if (-not $SyncMode) {
    $SyncMode = if ($envMap.ContainsKey('SYNC_MODE') -and $envMap['SYNC_MODE'] -in @('copy', 'sync')) { $envMap['SYNC_MODE'] } else { "copy" }
}

if (-not $BandwidthLimit -and $envMap.ContainsKey('BANDWIDTH_LIMIT')) {
    $BandwidthLimit = $envMap['BANDWIDTH_LIMIT']
}

if (-not $LogLevel) {
    $LogLevel = if ($envMap.ContainsKey('LOG_LEVEL') -and $envMap['LOG_LEVEL'] -in @('DEBUG', 'INFO', 'NOTICE', 'ERROR')) { $envMap['LOG_LEVEL'] } else { "INFO" }
}

if (-not $LogFile) {
    $LogFile = if ($envMap.ContainsKey('LOG_FILE')) { $envMap['LOG_FILE'] } else { "storage/logs/rclone_media_sync.log" }
}

# Resolusi path relatif terhadap root proyek
function Resolve-ToProjectRoot([string]$path) {
    $p = $path.Replace('\', '/')
    if ($p -match '^[a-zA-Z]:/' -or $p.StartsWith('/')) {
        return $p
    }
    return "$ProjectRoot/$p"
}

$ConfigFullPath = Resolve-ToProjectRoot $ConfigPath
$LocalFullPath  = Resolve-ToProjectRoot $LocalPath
$LogFullPath    = Resolve-ToProjectRoot $LogFile

# Pastikan direktori log tersedia
$LogDir = Split-Path -Parent $LogFullPath
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  JAGAPADI Media Sync - Pull Model Backup (cPanel -> Local)  " -ForegroundColor Cyan
Write-Host "  Waktu Mulai: $timestamp                                   " -ForegroundColor Gray
Write-Host "============================================================" -ForegroundColor Cyan

# 4. Pre-Flight Check 1: Deteksi rclone.exe
$rcloneBinary = $null
if ($RcloneExe -and (Test-Path $RcloneExe)) {
    $rcloneBinary = $RcloneExe
} else {
    $candidates = @(
        "C:\laragon\bin\rclone.exe",
        "C:\Program Files\rclone\rclone.exe",
        "C:\rclone\rclone.exe"
    )
    foreach ($cand in $candidates) {
        if (Test-Path $cand) {
            $rcloneBinary = $cand
            break
        }
    }
    if (-not $rcloneBinary) {
        $cmd = Get-Command rclone -ErrorAction SilentlyContinue
        if ($cmd) {
            $rcloneBinary = $cmd.Source
        }
    }
}

if (-not $rcloneBinary) {
    Write-Host "[ERROR] Binary rclone.exe tidak ditemukan di PATH atau C:\laragon\bin\rclone.exe!" -ForegroundColor Red
    Write-Host "[PETUNJUK] Jalankan skrip instalasi otomatis: powershell -File scripts/ensure_rclone.ps1" -ForegroundColor Yellow
    Write-Host "           Atau unduh manual dari https://rclone.org/downloads/ dan simpan di C:\laragon\bin\rclone.exe" -ForegroundColor Yellow
    exit 1
}

Write-Host "[PRE-FLIGHT] Rclone Binary : $rcloneBinary" -ForegroundColor Green

# 5. Pre-Flight Check 2: Deteksi File Konfigurasi Rclone
if (-not (Test-Path $ConfigFullPath)) {
    Write-Host "[ERROR] File konfigurasi tidak ditemukan: $ConfigFullPath" -ForegroundColor Red
    Write-Host "[PETUNJUK] Salin template dan sesuaikan kredensial cPanel Anda:" -ForegroundColor Yellow
    Write-Host "           Copy-Item config\rclone.conf.example config\rclone.conf" -ForegroundColor Yellow
    Write-Host "           Lalu gunakan 'rclone obscure' untuk password akun hosting Anda." -ForegroundColor Yellow
    exit 1
}

Write-Host "[PRE-FLIGHT] Config File   : $ConfigFullPath" -ForegroundColor Green

# 6. Pre-Flight Check 3: Deteksi / Buat Target Folder Lokal
if (-not (Test-Path $LocalFullPath)) {
    Write-Host "[INFO] Folder target lokal belum ada. Membuat: $LocalFullPath" -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $LocalFullPath -Force | Out-Null
}

$dryRunLabel = if ($DryRun) { " [DRY-RUN]" } else { "" }
Write-Host "[PRE-FLIGHT] Remote Source : $($RemoteName):$($RemotePath)" -ForegroundColor Green
Write-Host "[PRE-FLIGHT] Local Target  : $LocalFullPath" -ForegroundColor Green
Write-Host "[PRE-FLIGHT] Mode Operasi  : $SyncMode$dryRunLabel" -ForegroundColor Yellow
if ($BandwidthLimit) {
    Write-Host "[PRE-FLIGHT] Bandwidth     : Limit $BandwidthLimit" -ForegroundColor Yellow
}
Write-Host "[PRE-FLIGHT] Log File      : $LogFullPath" -ForegroundColor Gray
Write-Host "------------------------------------------------------------" -ForegroundColor Cyan

# 7. Susun Argumen CLI Rclone
$rcloneArgs = @(
    $SyncMode,
    "$($RemoteName):$($RemotePath)",
    $LocalFullPath,
    "--config=$ConfigFullPath",
    "--update",
    "--use-mtime",
    "--transfers=4",
    "--checkers=8",
    "--contimeout=30s",
    "--timeout=10m",
    "--retries=3",
    "--low-level-retries=10",
    "--stats=10s",
    "--stats-one-line",
    "--log-file=$LogFullPath",
    "--log-level=$LogLevel"
)

if ($DryRun) {
    $rcloneArgs += "--dry-run"
}

if ($BandwidthLimit) {
    $rcloneArgs += "--bwlimit=$BandwidthLimit"
}

# 8. Eksekusi Rclone
Write-Host "[RUNNING] Memulai sinkronisasi..." -ForegroundColor Cyan

$startTime = Get-Date
$process = Start-Process -FilePath $rcloneBinary -ArgumentList $rcloneArgs -NoNewWindow -PassThru -Wait
$exitCode = $process.ExitCode
$endTime = Get-Date
$duration = [math]::Round(($endTime - $startTime).TotalSeconds, 2)

Write-Host "------------------------------------------------------------" -ForegroundColor Cyan
if ($exitCode -eq 0) {
    Write-Host "[SUCCESS] Sinkronisasi selesai dengan sukses! Durasi: $duration detik" -ForegroundColor Green
    if (Test-Path $LogFullPath) {
        Write-Host "[INFO] Catatan log terbaru:" -ForegroundColor Gray
        Get-Content $LogFullPath -Tail 5 | ForEach-Object { Write-Host "  $_" -ForegroundColor DarkGray }
    }
} else {
    Write-Host "[FAILED] Sinkronisasi gagal dengan exit code: $exitCode. Durasi: $duration detik" -ForegroundColor Red
    if (Test-Path $LogFullPath) {
        Write-Host "[INFO] 10 Baris Log Terakhir:" -ForegroundColor Yellow
        Get-Content $LogFullPath -Tail 10 | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
    }
}
Write-Host "============================================================" -ForegroundColor Cyan

exit $exitCode
