@echo off
setlocal

set "ROOT=%~dp0"
set "BACKEND=%ROOT%backend"
set "WEBAPP=%ROOT%webapp"

where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP was not found in PATH.
    pause
    exit /b 1
)

where npm.cmd >nul 2>&1
if errorlevel 1 (
    echo [ERROR] npm was not found in PATH.
    pause
    exit /b 1
)

if not exist "%BACKEND%\vendor\autoload.php" (
    echo [ERROR] Backend dependencies are missing. Run composer install in backend.
    pause
    exit /b 1
)

if not exist "%WEBAPP%\node_modules" (
    echo [ERROR] Web app dependencies are missing. Run npm install in webapp.
    pause
    exit /b 1
)

call :port_in_use 8000
if errorlevel 1 (
    echo PHP API is already running on http://127.0.0.1:8000
) else (
    echo Starting PHP API on http://127.0.0.1:8000 ...
    start "ONESALEZ PHP API" /min cmd /k "cd /d ""%BACKEND%"" && php -S 127.0.0.1:8000 -t public"
)

call :port_in_use 5173
if errorlevel 1 (
    echo Web app is already running on http://127.0.0.1:5173
) else (
    echo Starting web app on http://127.0.0.1:5173 ...
    start "ONESALEZ Web App" /min cmd /k "cd /d ""%WEBAPP%"" && npm.cmd run dev -- --host 127.0.0.1"
)

powershell.exe -NoProfile -Command "Start-Sleep -Seconds 3"
start "" "http://127.0.0.1:5173/login"

echo.
echo Development services are ready.
echo API:     http://127.0.0.1:8000/api/v1/health
echo Web app: http://127.0.0.1:5173/login
exit /b 0

:port_in_use
powershell.exe -NoProfile -Command "if (Get-NetTCPConnection -State Listen -LocalPort %1 -ErrorAction SilentlyContinue) { exit 1 } else { exit 0 }"
exit /b %errorlevel%
