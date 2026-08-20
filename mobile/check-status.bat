@echo off
setlocal EnableDelayedExpansion

:: ============================================================
:: JAGAPADI — Cek Status Semua Komponen
:: Jalankan kapan saja untuk diagnosis cepat
:: ============================================================

set ADB=C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools\adb.exe
set FLUTTER=C:\flutter\bin\flutter.bat
set EMULATOR=C:\Users\IPDS\AppData\Local\Android\Sdk\emulator\emulator.exe

echo.
echo ================================================
echo   JAGAPADI — Status Check
echo ================================================
echo.

:: ── 1. Flutter ─────────────────────────────────────
echo [1] Flutter
if exist "%FLUTTER%" (
    echo   Path  : %FLUTTER%   [OK]
    for /f "tokens=*" %%V in ('%FLUTTER% --version 2^>nul ^| findstr "Flutter"') do echo   Ver   : %%V
) else (
    echo   [TIDAK DITEMUKAN] %FLUTTER%
)
echo.

:: ── 2. ADB ────────────────────────────────────────
echo [2] ADB
if exist "%ADB%" (
    echo   Path  : %ADB%   [OK]
    for /f "tokens=*" %%V in ('%ADB% version 2^>nul ^| findstr "Android Debug"') do echo   Ver   : %%V
) else (
    echo   [TIDAK DITEMUKAN] %ADB%
)
echo.

:: ── 3. Emulator ───────────────────────────────────
echo [3] Android Emulator
if exist "%EMULATOR%" (
    echo   Path  : %EMULATOR%   [OK]
    echo   AVD tersedia:
    for /f "delims=" %%A in ('%EMULATOR% -list-avds 2^>nul') do echo     - %%A
) else (
    echo   [TIDAK DITEMUKAN] %EMULATOR%
    echo   Install via: Android Studio ^> SDK Manager ^> SDK Tools ^> Android Emulator
)
echo.

:: ── 4. Perangkat terhubung ─────────────────────────
echo [4] Perangkat Android Terhubung
%ADB% devices 2>nul
set DEV_COUNT=0
for /f "skip=1 tokens=1,2" %%A in ('%ADB% devices 2^>nul') do (
    if not "%%A"=="" if not "%%B"=="" set /a DEV_COUNT+=1
)
if !DEV_COUNT!==0 (
    echo   [TIDAK ADA] Tidak ada emulator atau HP yang terhubung.
    echo   Jalankan start-avd.bat untuk memulai emulator.
) else (
    echo   [OK] !DEV_COUNT! perangkat terdeteksi.
)
echo.

:: ── 5. Server Laragon ──────────────────────────────
echo [5] Server Laragon (Apache port 80)
curl -s --max-time 3 http://localhost/jagapadi-3509/api/v1/health > nul 2>&1
if %ERRORLEVEL% == 0 (
    echo   http://localhost/jagapadi-3509/api/v1/health   [OK]
    curl -s --max-time 3 http://localhost/jagapadi-3509/api/v1/health 2>nul
    echo.
) else (
    echo   [GAGAL] Server tidak merespons.
    echo   Buka Laragon ^> Start All ^> pastikan Apache hijau.
)
echo.

:: ── 6. Ringkasan ──────────────────────────────────
echo ================================================
echo   Langkah selanjutnya:
echo ================================================

if !DEV_COUNT!==0 (
    echo.
    echo   Belum ada perangkat. Pilih salah satu:
    echo.
    echo   A. Emulator:
    echo      Jalankan: start-avd.bat
    echo      Tunggu emulator booting, lalu: run-emulator.bat
    echo.
    echo   B. HP fisik via USB:
    echo      1. Aktifkan USB Debugging di HP
    echo      2. Sambungkan kabel USB
    echo      3. Jalankan: run-physical-device.bat
) else (
    echo.
    echo   Perangkat siap! Jalankan:
    echo     run-emulator.bat
)
echo.
pause
