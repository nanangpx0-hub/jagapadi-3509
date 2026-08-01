@echo off
REM JAGAPADI E2E Test Runner
REM Usage: run-tests.bat [test-file-filter] [--skip-server]

setlocal enabledelayedexpansion

set PROJECT_ROOT=%~dp0..
set PHP_EXE=C:\laragon\bin\php\php-8.2.32-nts-Win32-vs16-x64\php.exe
set NPX_CMD=C:\laragon\bin\nodejs\node-v18\npx.cmd
set PLAYWRIGHT_BIN=%PROJECT_ROOT%\node_modules\.bin\playwright.cmd
set SKIP_SERVER=0

REM Parse args
set TEST_FILTER=
:parse_args
if "%~1"=="--skip-server" (
    set SKIP_SERVER=1
    shift
    goto parse_args
)
if not "%~1"=="" (
    set TEST_FILTER=%~1
)

REM Start PHP server if needed
if %SKIP_SERVER%==0 (
    echo Starting PHP server on localhost:8080...
    start "" "%PHP_EXE%" -S localhost:8080 -t "%PROJECT_ROOT%\backend\public"
    timeout /t 3 /nobreak > nul
    
    REM Verify server
    curl -s -o nul -w "%%{http_code}" http://localhost:8080/login > %TEMP%\php_test.txt
    set /p PHP_STATUS=<%TEMP%\php_test.txt
    if not "!PHP_STATUS!"=="200" (
        if "!PHP_STATUS!"=="302" (
            echo   [OK] PHP server is running
        ) else (
            echo   [FAIL] PHP server returned !PHP_STATUS!
        )
    ) else (
        echo   [OK] PHP server is running
    )
)

REM Choose test runner
set RUNNER=%PLAYWRIGHT_BIN%
if not exist "%PLAYWRIGHT_BIN%" (
    set RUNNER=%NPX_CMD%
)

REM Run tests
cd /d "%~dp0"
echo Running Playwright tests...

if defined TEST_FILTER (
    "%RUNNER%" test "%TEST_FILTER%"
) else (
    "%RUNNER%" test
)

set EXIT_CODE=%ERRORLEVEL%

REM Cleanup PHP server
if %SKIP_SERVER%==0 (
    echo Stopping PHP server...
    taskkill /f /im php-8.2.32-nts-Win32-vs16-x64.exe /fi "WindowTitle eq *localhost:8080*" > nul 2>&1
)

echo Done. Exit code: %EXIT_CODE%
exit /b %EXIT_CODE%
