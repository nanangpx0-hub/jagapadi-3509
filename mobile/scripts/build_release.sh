#!/bin/bash
set -e

echo "=== JAGAPADI Mobile Release Build Script ==="

API_URL="${1:-https://jagapadi.jemberkab.go.id/api/v1}"

echo "[1/4] Cleaning previous builds..."
flutter clean

echo "[2/4] Getting dependencies..."
flutter pub get

echo "[3/4] Building release APKs for production..."
flutter build apk --release \
  --dart-define=API_BASE_URL="$API_URL" \
  --split-per-abi

echo "[4/4] Copying APKs to dist/..."
mkdir -p dist
cp build/app/outputs/flutter-apk/app-arm64-v8a-release.apk dist/jagapadi-arm64-v8a-release.apk
cp build/app/outputs/flutter-apk/app-armeabi-v7a-release.apk dist/jagapadi-armeabi-v7a-release.apk
cp build/app/outputs/flutter-apk/app-x86_64-release.apk dist/jagapadi-x86_64-release.apk

echo "=== SHA256 Checksums ==="
sha256sum dist/*.apk

echo "=== Build Complete! APKs stored in mobile/dist/ ==="
