@echo off
setlocal
rem ============================================================================
rem  DORY: stops only what start_dory.bat started. XAMPP, MariaDB, MySQL,
rem  Chrome, adb and other programs are never touched.
rem  Details: docs\LOCAL_DEVELOPMENT_STARTUP.md
rem ============================================================================
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\dev\stop_dory.ps1" %*
set "DORY_EXIT=%ERRORLEVEL%"
echo.
if "%DORY_NO_PAUSE%"=="" pause
exit /b %DORY_EXIT%
