@echo off
setlocal

:: ============================================================
:: JAGAPADI Mobile — Build APK
:: Usage:
::   build-apk.bat                 -> debug, target LAN (192.168.10.5)
::   build-apk.bat emulator debug  -> debug, URL emulator
::   build-apk.bat lan debug       -> debug, IP LAN 192.168.10.5
::   build-apk.bat lan release     -> release, IP LAN
::   build-apk.bat prod release    -> release, URL produksi
:: ============================================================

set FLUTTER=C:\flutter\bin\flutter.bat
set PATH=C:\flutter\bin;C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools;%PATH%
set ANDROID_HOME=C:\Users\IPDS\AppData\Local\Android\Sdk
set ANDROID_SDK_ROOT=C:\Users\IPDS\AppData\Local\Android\Sdk

set TARGET=%1
set BUILDTYPE=%2
if "%TARGET%"==""   set TARGET=lan
if "%BUILDTYPE%"="" set BUILDTYPE=debug

:: URL tanpa port (Laragon port 80)
if "%TARGET%"=="emulator" set API_URL=http://10.0.2.2/jagapadi-3509/api/v1
if "%TARGET%"=="lan"      set API_URL=http://192.168.10.5/jagapadi-3509/api/v1
if "%TARGET%"=="prod"     set API_URL=https://jagapadi.example.go.id/api/v1

if "%API_URL%"=="" (
    echo Target tidak valid: %TARGET%
    echo Gunakan: emulator ^| lan ^| prod
    pause & exit /b 1
)

echo.
echo === JAGAPADI APK Builder ===
echo Target  : %TARGET%
echo Type    : %BUILDTYPE%
echo API URL : %API_URL%
echo.

if "%TARGET%"=="prod" if "%BUILDTYPE%"=="release" (
    echo [PERHATIAN] Build RELEASE untuk PRODUKSI
    echo Pastikan key.properties sudah dikonfigurasi!
    set /p OK="Lanjutkan? (y/N): "
    if /i not "%OK%"=="y" ( echo Dibatalkan. & exit /b 0 )
)

cd /d C:\laragon\www\jagapadi-3509\mobile

echo -- flutter pub get --
%FLUTTER% pub get
if %ERRORLEVEL% neq 0 ( echo GAGAL: flutter pub get. & pause & exit /b 1 )

echo.
echo -- Building APK (%BUILDTYPE%) --

if "%BUILDTYPE%"=="release" (
    %FLUTTER% build apk --release --split-per-abi --dart-define=API_BASE_URL=%API_URL%
) else (
    %FLUTTER% build apk --debug --dart-define=API_BASE_URL=%API_URL%
)

if %ERRORLEVEL% == 0 (
    echo.
    echo === BUILD BERHASIL ===
    echo APK ada di: build\app\outputs\flutter-apk\
    dir /b build\app\outputs\flutter-apk\*.apk 2>nul
) else (
    echo.
    echo === BUILD GAGAL === Cek output di atas.
)

endlocal
pause
