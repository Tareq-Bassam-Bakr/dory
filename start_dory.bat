@echo off
setlocal
rem ============================================================================
rem  DORY: starts the local development environment with one double-click.
rem  Laravel API, Reverb, queue worker and the Flutter app, each in its own
rem  window (DORY-API, DORY-REVERB, DORY-QUEUE, DORY-FLUTTER).
rem  Details: docs\LOCAL_DEVELOPMENT_STARTUP.md
rem
rem  Options (from a terminal): start_dory.bat -NoFlutter, -Emulator,
rem  -Device <id>, -AssumeYes
rem ============================================================================
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\dev\start_dory.ps1" %*
set "DORY_EXIT=%ERRORLEVEL%"
echo.
if not "%DORY_EXIT%"=="0" echo DORY did not start completely. Read the messages above.
if "%DORY_NO_PAUSE%"=="" pause
exit /b %DORY_EXIT%
