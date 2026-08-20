@echo off
echo === JAGAPADI Mobile Release Build Script (Windows) ===

set API_URL=%1
if "%API_URL%"=="" set API_URL=https://jagapadi.jemberkab.go.id/api/v1

echo [1/4] Cleaning previous builds...
call flutter clean

echo [2/4] Getting dependencies...
call flutter pub get

echo [3/4] Building release APKs for production...
call flutter build apk --release --dart-define=API_BASE_URL=%API_URL% --split-per-abi

echo [4/4] Copying APKs to dist...
if not exist "dist" mkdir dist
copy /Y "build\app\outputs\flutter-apk\app-arm64-v8a-release.apk" "dist\jagapadi-arm64-v8a-release.apk"
copy /Y "build\app\outputs\flutter-apk\app-armeabi-v7a-release.apk" "dist\jagapadi-armeabi-v7a-release.apk"
copy /Y "build\app\outputs\flutter-apk\app-x86_64-release.apk" "dist\jagapadi-x86_64-release.apk"

echo === Build Complete! APKs stored in mobile\dist\ ===
pause
