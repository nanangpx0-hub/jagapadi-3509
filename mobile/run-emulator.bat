@echo off
setlocal

:: ============================================================
:: JAGAPADI Mobile — Jalankan di Emulator/Perangkat Android
:: Laragon berjalan di port 80 (Apache default)
:: ============================================================

set FLUTTER=C:\flutter\bin\flutter.bat
set ADB=C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools\adb.exe
set PATH=C:\flutter\bin;C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools;%PATH%
set ANDROID_HOME=C:\Users\IPDS\AppData\Local\Android\Sdk
set ANDROID_SDK_ROOT=C:\Users\IPDS\AppData\Local\Android\Sdk

:: URL tanpa port (Laragon Apache port 80)
set API_URL_EMULATOR=http://10.0.2.2/jagapadi-3509/api/v1
set API_URL_LAN=http://192.168.10.5/jagapadi-3509/api/v1
set HEALTH_URL=http://localhost/jagapadi-3509/api/v1/health

echo.
echo === JAGAPADI Mobile Runner ===
echo.

:: -- Cek tools --
if not exist "%FLUTTER%" (
    echo [GAGAL] flutter.bat tidak ditemukan: %FLUTTER%
    pause & exit /b 1
)
if not exist "%ADB%" (
    echo [GAGAL] adb.exe tidak ditemukan: %ADB%
    pause & exit /b 1
)
echo [OK] Flutter: %FLUTTER%
echo [OK] ADB    : %ADB%

:: -- Cek server Laragon --
echo.
echo -- Mengecek server Laragon --
curl -s --max-time 3 "%HEALTH_URL%" > nul 2>&1
if %ERRORLEVEL% == 0 (
    echo [OK] Server Laragon aktif: %HEALTH_URL%
) else (
    echo [PERINGATAN] Server tidak merespons di %HEALTH_URL%
    echo.
    echo Pastikan:
    echo   1. Buka Laragon ^> klik "Start All" ^> Apache harus hijau
    echo   2. Coba buka di browser: %HEALTH_URL%
    echo   3. Jika 404: pastikan folder jagapadi-3509 ada di C:\laragon\www\
    echo.
    set /p GO="Lanjutkan quand meme? (y/N): "
    if /i not "%GO%"=="y" ( echo Dibatalkan. & pause & exit /b 1 )
)

:: -- Deteksi perangkat --
echo.
echo -- Mendeteksi perangkat Android --
%ADB% devices 2>&1

:: Cek apakah ada perangkat sama sekali
for /f "skip=1 tokens=*" %%D in ('%ADB% devices 2^>nul') do (
    set DEVICE_LINE=%%D
    goto :found
)
echo.
echo [TIDAK ADA PERANGKAT]
echo Buka Android Studio ^> Device Manager ^> Start AVD
echo Atau sambungkan HP via USB dengan USB Debugging aktif.
pause & exit /b 1

:found
:: Pilih URL berdasarkan jenis perangkat
%ADB% devices | findstr "emulator" > nul 2>&1
if %ERRORLEVEL% == 0 (
    set TARGET_URL=%API_URL_EMULATOR%
    echo.
    echo [Emulator] URL: %API_URL_EMULATOR%
) else (
    :: Coba adb reverse untuk perangkat fisik
    %ADB% reverse tcp:80 tcp:80 > nul 2>&1
    if %ERRORLEVEL% == 0 (
        set TARGET_URL=%API_URL_EMULATOR%
        echo [Fisik + adb reverse] URL: %API_URL_EMULATOR%
    ) else (
        set TARGET_URL=%API_URL_LAN%
        echo [Fisik + LAN] URL: %API_URL_LAN%
        echo PENTING: Pastikan HP di Wi-Fi yang sama dengan komputer ini!
    )
)

:: -- Jalankan Flutter --
echo.
echo -- flutter run --
echo API_BASE_URL = %TARGET_URL%
echo (Tekan r=reload, R=restart, q=quit)
echo.

cd /d C:\laragon\www\jagapadi-3509\mobile
%FLUTTER% run --dart-define=API_BASE_URL=%TARGET_URL%

endlocal
pause
