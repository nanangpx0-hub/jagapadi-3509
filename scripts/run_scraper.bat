@echo off
REM ==================================
REM JAGAPADI Curah Hujan Scraper
REM Scheduled Task Script for Windows
REM ==================================
REM 
REM Setup di Windows Task Scheduler:
REM 1. Buka Task Scheduler (taskschd.msc)
REM 2. Create Task > Name: "JAGAPADI Curah Hujan Scraper"
REM 3. Triggers > New > Daily, jam 23:00
REM 4. Actions > New > Start Program
REM    Program: C:\laragon\www\jagapadi\scripts\run_scraper.bat
REM 5. Conditions > Uncheck "Start only if on AC power"
REM ==================================

cd /d C:\laragon\www\jagapadi

echo =========================================
echo JAGAPADI - Curah Hujan Scraper
echo Waktu: %date% %time%
echo =========================================

C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe scripts\cron_curah_hujan.php

echo =========================================
echo Scraper selesai
echo =========================================

REM Log output ke file
echo [%date% %time%] Scraper executed >> logs\scheduled_tasks.log
