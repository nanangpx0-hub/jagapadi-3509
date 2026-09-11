@echo off
setlocal
:: JAGAPADI Mobile — Build APK Petugas via Cloudflare
:: API: https://jagapadi.my.id/api/v1  (tunnel -> http://localhost:8080)
:: Dashboard: https://jagapadi.my.id/dashboard
set FLUTTER=C:\flutter\bin\flutter.bat
set API_URL=https://jagapadi.my.id/api/v1
set BUILDTYPE=%1
if "%BUILDTYPE%"=="" set BUILDTYPE=debug

echo === JAGAPADI PETUGAS Cloudflare Build ===
echo API  : %API_URL%
echo Build: %BUILDTYPE%
echo Tunnel: cloudflared tunnel --url http://localhost:8080
echo.

cd /d C:\laragon\www\jagapadi-3509\mobile
echo -- flutter pub get --
%FLUTTER% pub get
if %ERRORLEVEL% neq 0 (echo GAGAL pub get & pause & exit /b 1)

echo -- Building APK Petugas (%BUILDTYPE%) --
if "%BUILDTYPE%"=="release" (
  %FLUTTER% build apk --release --split-per-abi --dart-define=API_BASE_URL=%API_URL%
) else (
  %FLUTTER% build apk --debug --dart-define=API_BASE_URL=%API_URL%
)

if %ERRORLEVEL%==0 (
  echo === BUILD BERHASIL ===
  dir /b build\app\outputs\flutter-apk\*.apk
) else (
  echo === BUILD GAGAL ===
)
pause
