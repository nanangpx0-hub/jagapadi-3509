@echo off
setlocal

:: ============================================================
:: JAGAPADI — Cek dan Jalankan Android Emulator (AVD)
:: Jalankan file ini dari CMD, lalu tunggu emulator terbuka
:: Setelah emulator siap, jalankan run-emulator.bat
:: ============================================================

set ADB=C:\Users\IPDS\AppData\Local\Android\Sdk\platform-tools\adb.exe
set EMULATOR=C:\Users\IPDS\AppData\Local\Android\Sdk\emulator\emulator.exe
set AVD_DIR=C:\Users\IPDS\.android\avd

echo.
echo === JAGAPADI — Android Emulator Setup ===
echo.

:: -- Cek emulator.exe ada --
if not exist "%EMULATOR%" (
    echo [GAGAL] emulator.exe tidak ditemukan di:
    echo   %EMULATOR%
    echo.
    echo Solusi:
    echo   Buka Android Studio ^> SDK Manager ^> SDK Tools
    echo   Centang "Android Emulator" ^> Apply
    echo.
    pause & exit /b 1
)

:: -- Daftar AVD yang tersedia --
echo -- Daftar AVD yang tersedia --
%EMULATOR% -list-avds 2>nul
echo.

:: Simpan daftar AVD ke variabel
set AVD_COUNT=0
set FIRST_AVD=

for /f "delims=" %%A in ('%EMULATOR% -list-avds 2^>nul') do (
    set /a AVD_COUNT+=1
    if !AVD_COUNT!==1 set FIRST_AVD=%%A
    echo   [%%A]
)

:: Gunakan delayed expansion untuk cek AVD_COUNT
setlocal EnableDelayedExpansion

set AVD_LIST=
set AVD_COUNT=0
for /f "delims=" %%A in ('%EMULATOR% -list-avds 2^>nul') do (
    set /a AVD_COUNT+=1
    if !AVD_COUNT!==1 set FIRST_AVD=%%A
)

if !AVD_COUNT!==0 (
    echo [TIDAK ADA AVD]
    echo.
    echo Cara membuat AVD baru:
    echo   1. Buka Android Studio
    echo   2. Menu: Tools ^> Device Manager
    echo   3. Klik "Create Device"
    echo   4. Pilih: Phone ^> Pixel 6 ^> Next
    echo   5. Pilih sistem: Android 11 (API 30) atau Android 14 (API 34) ^> Download jika perlu
    echo   6. Klik Finish
    echo   7. Jalankan start-avd.bat lagi
    echo.
    echo [ALTERNATIF] Gunakan perangkat fisik Android:
    echo   1. Di HP: Pengaturan ^> Tentang Ponsel ^> ketuk Nomor Build 7x
    echo   2. Pengaturan ^> Opsi Pengembang ^> USB Debugging = ON
    echo   3. Sambungkan kabel USB ke komputer
    echo   4. Jalankan run-physical-device.bat
    echo.
    pause & exit /b 1
)

echo Ditemukan !AVD_COUNT! AVD. Menggunakan: !FIRST_AVD!
echo.

:: -- Cek apakah emulator sudah berjalan --
%ADB% devices | findstr "emulator" > nul 2>&1
if %ERRORLEVEL% == 0 (
    echo [OK] Emulator sudah berjalan!
    echo Langsung jalankan: run-emulator.bat
    echo.
    pause & exit /b 0
)

:: -- Start emulator --
echo -- Menjalankan emulator: !FIRST_AVD! --
echo (Jendela emulator akan terbuka. Tunggu hingga layar home Android muncul)
echo (Proses ini bisa memakan waktu 1-3 menit)
echo.

start "" "%EMULATOR%" -avd "!FIRST_AVD!" -no-snapshot-load

echo Emulator sedang booting...
echo.
echo -- Menunggu emulator siap (maksimal 120 detik) --

set WAIT=0
:wait_loop
timeout /t 5 /nobreak > nul
set /a WAIT+=5
%ADB% devices | findstr "emulator.*device" > nul 2>&1
if %ERRORLEVEL% == 0 goto :emu_ready
if !WAIT! geq 120 goto :emu_timeout
echo   Masih menunggu... (!WAIT!s)
goto :wait_loop

:emu_ready
echo.
echo [OK] Emulator siap setelah !WAIT! detik!
echo.
echo Sekarang jalankan:
echo   run-emulator.bat
echo.
pause & exit /b 0

:emu_timeout
echo.
echo [TIMEOUT] Emulator belum siap setelah 120 detik.
echo Tunggu sampai layar home Android muncul di emulator,
echo lalu jalankan run-emulator.bat secara manual.
echo.
pause & exit /b 0

endlocal
