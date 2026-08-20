@echo off
:: ============================================================
:: JAGAPADI — Setup PATH Permanen (Flutter + ADB)
:: Jalankan sekali sebagai Administrator
:: Setelah selesai, buka terminal baru — flutter dan adb aktif
:: ============================================================

echo.
echo === Setup PATH Permanen JAGAPADI ===
echo.

:: Cek apakah dijalankan sebagai Administrator
net session >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [INFO] Tidak dijalankan sebagai Administrator.
    echo Akan menambahkan ke PATH User (bukan System) - ini sudah cukup.
    echo.
)

set FLUTTER_BIN=C:\flutter\bin
set ADB_TOOLS=C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools
set ANDROID_SDK=C:\Users\IPDS\AppData\Local\Android\Sdk

:: Cek keberadaan path
if not exist "%FLUTTER_BIN%\flutter.bat" (
    echo [GAGAL] Flutter tidak ditemukan di %FLUTTER_BIN%
    pause
    exit /b 1
)
echo [OK] Flutter : %FLUTTER_BIN%

if not exist "%ADB_TOOLS%\adb.exe" (
    echo [GAGAL] ADB tidak ditemukan di %ADB_TOOLS%
    pause
    exit /b 1
)
echo [OK] ADB     : %ADB_TOOLS%

:: Tambahkan ke PATH User secara permanen via setx
echo.
echo -- Menambahkan ke PATH User (permanen) --

:: Baca PATH user saat ini
for /f "tokens=2*" %%A in ('reg query "HKCU\Environment" /v PATH 2^>nul') do set CURRENT_PATH=%%B

:: Cek apakah sudah ada
echo %CURRENT_PATH% | findstr /i "flutter\bin" >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo (Flutter sudah ada di PATH)
) else (
    setx PATH "%FLUTTER_BIN%;%CURRENT_PATH%" >nul
    echo [OK] Flutter ditambahkan ke PATH permanen
)

:: Baca ulang setelah update pertama
for /f "tokens=2*" %%A in ('reg query "HKCU\Environment" /v PATH 2^>nul') do set CURRENT_PATH=%%B
echo %CURRENT_PATH% | findstr /i "platform-tools" >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo (ADB sudah ada di PATH)
) else (
    setx PATH "%ADB_TOOLS%;%CURRENT_PATH%" >nul
    echo [OK] ADB ditambahkan ke PATH permanen
)

:: Set ANDROID_HOME
setx ANDROID_HOME "%ANDROID_SDK%" >nul
setx ANDROID_SDK_ROOT "%ANDROID_SDK%" >nul
echo [OK] ANDROID_HOME = %ANDROID_SDK%

echo.
echo === SELESAI ===
echo.
echo Buka terminal BARU lalu verifikasi dengan:
echo   flutter --version
echo   adb version
echo.
echo Atau langsung jalankan (tanpa buka terminal baru):
echo   run-emulator.bat
echo   run-physical-device.bat
echo.
pause
