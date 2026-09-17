<#
.SYNOPSIS
    Memeriksa dan menginstal rclone di C:\laragon\bin serta memastikan dapat dipanggil dari CLI.

.DESCRIPTION
    Skrip otomasi untuk lingkungan Laragon / Windows di repositori JAGAPADI.
    Mengunduh binary resmi rclone Windows amd64, mengekstrak rclone.exe ke C:\laragon\bin,
    memperbarui User PATH jika belum terdaftar, dan memvalidasi perintah `rclone version`.

.PARAMETER InstallDir
    Direktori tujuan instalasi binary. Default: C:\laragon\bin

.PARAMETER Force
    Paksa unduh ulang meskipun binary rclone sudah ada.
#>

[CmdletBinding()]
param(
    [string]$InstallDir = "C:\laragon\bin",
    [switch]$Force
)

$ErrorActionPreference = 'Stop'

Write-Host "=== [JAGAPADI] Rclone Environment Setup ===" -ForegroundColor Cyan

$rcloneTarget = Join-Path $InstallDir "rclone.exe"

# Helper untuk memastikan direktori terdaftar di User PATH dan session PATH
function Ensure-PathRegistered {
    param([string]$PathToAdd)

    $normalizedTarget = $PathToAdd.TrimEnd('\')
    
    # 1. User PATH (persisten)
    $userPath = [Environment]::GetEnvironmentVariable("Path", "User")
    $userPathList = ($userPath -split ';') | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    $foundInUser = $false
    foreach ($p in $userPathList) {
        if ($p.TrimEnd('\') -ieq $normalizedTarget) {
            $foundInUser = $true
            break
        }
    }
    if (-not $foundInUser) {
        Write-Host "[INFO] Menambahkan $PathToAdd ke User PATH..." -ForegroundColor Cyan
        $newUserPath = if ([string]::IsNullOrWhiteSpace($userPath)) { $PathToAdd } else { "$userPath;$PathToAdd" }
        [Environment]::SetEnvironmentVariable("Path", $newUserPath, "User")
    }

    # 2. Session PATH
    $sessionPathList = ($env:Path -split ';') | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    $foundInSession = $false
    foreach ($p in $sessionPathList) {
        if ($p.TrimEnd('\') -ieq $normalizedTarget) {
            $foundInSession = $true
            break
        }
    }
    if (-not $foundInSession) {
        $env:Path = "$PathToAdd;$env:Path"
    }
}

# 1. Periksa apakah rclone sudah ada
$rcloneFound = $false
$existingPath = $null

if (Test-Path $rcloneTarget) {
    $rcloneFound = $true
    $existingPath = $rcloneTarget
} else {
    $cmd = Get-Command rclone -ErrorAction SilentlyContinue
    if ($cmd) {
        $rcloneFound = $true
        $existingPath = $cmd.Source
    }
}

if ($rcloneFound -and -not $Force) {
    Ensure-PathRegistered -PathToAdd $InstallDir
    Write-Host "[OK] Rclone sudah terpasang di: $existingPath" -ForegroundColor Green
    try {
        $ver = & $existingPath version
        Write-Host ($ver | Out-String) -ForegroundColor Gray
        return
    } catch {
        Write-Warning "Binary rclone ditemukan tetapi gagal dieksekusi. Melanjutkan instalasi ulang..."
    }
}

# 2. Siapkan direktori instalasi
if (-not (Test-Path $InstallDir)) {
    Write-Host "[INFO] Membuat direktori: $InstallDir" -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $InstallDir -Force | Out-Null
}

# 3. Unduh rclone resmi Windows amd64
$zipUrl = "https://downloads.rclone.org/rclone-current-windows-amd64.zip"
$tempZip = Join-Path ([System.IO.Path]::GetTempPath()) "rclone-current-windows-amd64.zip"
$tempExtractDir = Join-Path ([System.IO.Path]::GetTempPath()) ("rclone-extract-" + [System.Guid]::NewGuid().ToString("N"))

Write-Host "[INFO] Mengunduh rclone dari: $zipUrl" -ForegroundColor Cyan
try {
    # Gunakan WebClient atau Invoke-WebRequest
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12 -bor [System.Net.SecurityProtocolType]::Tls13
    $webClient = New-Object System.Net.WebClient
    $webClient.DownloadFile($zipUrl, $tempZip)
} catch {
    Write-Host "[FALLBACK] Gagal dengan WebClient, mencoba curl.exe / Invoke-WebRequest..." -ForegroundColor Yellow
    if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
        & curl.exe -sSL -o $tempZip $zipUrl
    } else {
        Invoke-WebRequest -Uri $zipUrl -OutFile $tempZip -UseBasicParsing
    }
}

if (-not (Test-Path $tempZip)) {
    throw "Gagal mengunduh file zip rclone dari $zipUrl"
}

# 4. Ekstrak arsip
Write-Host "[INFO] Mengekstrak zip ke folder sementara..." -ForegroundColor Cyan
Expand-Archive -Path $tempZip -DestinationPath $tempExtractDir -Force

$extractedExe = Get-ChildItem -Path $tempExtractDir -Filter "rclone.exe" -Recurse | Select-Object -First 1
if (-not $extractedExe) {
    throw "File rclone.exe tidak ditemukan di dalam arsip zip yang diekstrak."
}

# 5. Pasang rclone.exe ke target direktori (C:\laragon\bin)
Write-Host "[INFO] Menyalin rclone.exe ke: $rcloneTarget" -ForegroundColor Cyan
Copy-Item -Path $extractedExe.FullName -Destination $rcloneTarget -Force

# 6. Bersihkan temporary files
try {
    Remove-Item -Path $tempZip -Force -ErrorAction SilentlyContinue
    Remove-Item -Path $tempExtractDir -Recurse -Force -ErrorAction SilentlyContinue
} catch {
    # Abaikan error pembersihan temp
}

# 7. Pastikan C:\laragon\bin terdaftar di PATH
Ensure-PathRegistered -PathToAdd $InstallDir

# 8. Verifikasi eksekusi
Write-Host "[SUCCESS] Rclone berhasil dipasang di $rcloneTarget!" -ForegroundColor Green
Write-Host "=== Output rclone version ===" -ForegroundColor Cyan
& $rcloneTarget version
