@echo off
setlocal

:: ============================================================
:: JAGAPADI Mobile — Perangkat Fisik Android
:: Laragon port 80, IP mesin: 192.168.10.5
:: ============================================================

set FLUTTER=C:\flutter\bin\flutter.bat
set ADB=C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools\adb.exe
set PATH=C:\flutter\bin;C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools;%PATH%
set ANDROID_HOME=C:\Users\IPDS\AppData\Local\Android\Sdk

set IP_LAN=192.168.10.5
set API_URL_REVERSE=http://10.0.2.2/jagapadi-3509/api/v1
set API_URL_LAN=http://%IP_LAN%/jagapadi-3509/api/v1

echo.
echo === JAGAPADI Mobile - Perangkat Fisik ===
echo IP komputer: %IP_LAN%
echo.

:: -- Cek perangkat USB --
echo -- Mendeteksi perangkat USB --
%ADB% devices 2>&1

for /f "skip=1 tokens=1,2" %%A in ('%ADB% devices 2^>nul') do (
    if "%%B"=="device" (
        echo [OK] Perangkat: %%A
        goto :device_found
    )
)

echo.
echo [TIDAK ADA PERANGKAT FISIK]
echo.
echo Checklist:
echo   ^[ ^] Kabel USB tersambung
echo   ^[ ^] Pengaturan ^> Tentang Ponsel ^> ketuk "Nomor Build" 7x
echo   ^[ ^] Pengaturan ^> Opsi Pengembang ^> USB Debugging = ON
echo   ^[ ^] Pilih "Transfer file/MTP" di notifikasi HP
echo   ^[ ^] Izinkan "Percayai komputer ini" jika muncul di HP
echo.
echo Cek ulang dengan: adb devices
pause & exit /b 1

:device_found
:: Coba adb reverse (HTTP port 80)
echo.
echo -- Mencoba adb reverse tcp:80 --
%ADB% reverse tcp:80 tcp:80 > nul 2>&1
if %ERRORLEVEL% == 0 (
    set TARGET_URL=%API_URL_REVERSE%
    echo [OK] adb reverse berhasil!
    echo     HP akses server via USB tunnel — tidak perlu Wi-Fi sama.
    echo     URL: %API_URL_REVERSE%
) else (
    set TARGET_URL=%API_URL_LAN%
    echo [INFO] adb reverse gagal ^(butuh USB Debugging aktif^).
    echo     Menggunakan IP LAN: %API_URL_LAN%
    echo.
    echo     PENTING: HP harus di Wi-Fi yang sama dengan komputer ini!
    echo     Test dari browser HP: http://%IP_LAN%/jagapadi-3509/api/v1/health
)

echo.
echo -- flutter run --
echo API_BASE_URL = %TARGET_URL%
echo.

cd /d C:\laragon\www\jagapadi-3509\mobile
%FLUTTER% run --dart-define=API_BASE_URL=%TARGET_URL%

endlocal
pause
