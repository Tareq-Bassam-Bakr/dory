<#
.SYNOPSIS
    Stops what start_dory.ps1 started, and nothing else
    (docs/LOCAL_DEVELOPMENT_STARTUP.md).

.DESCRIPTION
    Reads .dory\run\processes.json. A recorded process is stopped only if it
    is still the same window: same process id, same start time, and the
    DORY window title in its command line. XAMPP, MariaDB, MySQL, Chrome,
    adb and any other PHP process are never touched. The adb reverse rules
    the start added are removed; the adb server keeps running.
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$StateFile = Join-Path $Root '.dory\run\processes.json'

Write-Host 'Stopping DORY' -ForegroundColor White

if (-not (Test-Path $StateFile)) {
    Write-Host '   --  Nothing to stop: no record of a DORY start (.dory\run\processes.json).' -ForegroundColor Yellow
    exit 0
}

$state = Get-Content -Raw -Path $StateFile | ConvertFrom-Json

foreach ($service in @($state.services)) {
    $process = Get-CimInstance Win32_Process -Filter ("ProcessId = " + [int]$service.pid) -ErrorAction SilentlyContinue
    $label = '{0} (process {1})' -f $service.title, $service.pid
    if ($null -eq $process) {
        Write-Host "   --  $label had already ended." -ForegroundColor Yellow
        continue
    }
    $started = $process.CreationDate.ToUniversalTime().ToString('o')
    $ours = $process.Name -eq 'cmd.exe' -and
        $process.CommandLine -like ('*' + $service.title + '*') -and
        $started -eq $service.created
    if (-not $ours) {
        Write-Host "   --  $label is now another program: left alone." -ForegroundColor Yellow
        continue
    }
    # The window and what runs in it (artisan, php, flutter).
    & taskkill.exe /PID $service.pid /T /F | Out-Null
    Write-Host "   OK  Stopped $label" -ForegroundColor Green
}

$reverse = $null
if ($state.PSObject.Properties.Name -contains 'adb_reverse') {
    $reverse = $state.adb_reverse
}
if ($null -ne $reverse) {
    $adb = $null
    foreach ($sdk in @($env:ANDROID_HOME, $env:ANDROID_SDK_ROOT, (Join-Path $env:LOCALAPPDATA 'Android\Sdk'))) {
        if ($sdk -and (Test-Path (Join-Path $sdk 'platform-tools\adb.exe'))) {
            $adb = Join-Path $sdk 'platform-tools\adb.exe'
            break
        }
    }
    if ($null -ne $adb) {
        foreach ($port in @($reverse.ports)) {
            & $adb -s $reverse.serial reverse --remove "tcp:$port" 2>$null | Out-Null
        }
        Write-Host ("   OK  Removed adb reverse on {0}" -f $reverse.serial) -ForegroundColor Green
    }
}

Remove-Item -Path $StateFile -Force
Write-Host 'DORY stopped. XAMPP and other programs were not touched.' -ForegroundColor Green
exit 0
