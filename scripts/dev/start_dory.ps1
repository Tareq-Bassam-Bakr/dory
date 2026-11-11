<#
.SYNOPSIS
    Starts the local DORY development environment: Laravel API, Reverb,
    queue worker and the Flutter app (docs/LOCAL_DEVELOPMENT_STARTUP.md).

.DESCRIPTION
    Checks everything first, then opens one visible window per service:
    DORY-API, DORY-REVERB, DORY-QUEUE, DORY-SCHEDULER and DORY-FLUTTER. It
    records the
    processes it started in .dory\run\processes.json, so stop_dory.bat stops
    those and nothing else. Running it again never starts a second copy of
    a service that is still running.

    It never resets, wipes or seeds a database. Pending migrations are run
    only after you answer yes.

.PARAMETER Device
    Flutter device id. Default: the only connected phone, else the emulator.

.PARAMETER Emulator
    Prefer an Android emulator over a USB phone.

.PARAMETER NoFlutter
    Start the API, Reverb and the queue worker only.

.PARAMETER AssumeYes
    Answer yes to the pending-migrations question (unattended runs).
#>
[CmdletBinding()]
param(
    [string]$Device = '',
    [switch]$Emulator,
    [switch]$NoFlutter,
    [switch]$AssumeYes
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

# ----- Paths ------------------------------------------------------------------

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Backend = Join-Path $Root 'backend'
$RunDir = Join-Path $Root '.dory\run'
$LogDir = Join-Path $Root '.dory\logs'
$StateFile = Join-Path $RunDir 'processes.json'
$LocalConfig = Join-Path $PSScriptRoot 'dory.local.psd1'
$ApiPort = 8000
$ReverbPort = 8080

New-Item -ItemType Directory -Force -Path $RunDir, $LogDir | Out-Null
$LogFile = Join-Path $LogDir ('start-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')

function Write-Step([string]$Text) {
    Write-Host ''
    Write-Host "== $Text" -ForegroundColor Cyan
    Add-Content -Path $LogFile -Value "== $Text"
}

function Write-Ok([string]$Text) {
    Write-Host "   OK  $Text" -ForegroundColor Green
    Add-Content -Path $LogFile -Value "   OK  $Text"
}

function Write-Note([string]$Text) {
    Write-Host "   --  $Text" -ForegroundColor Yellow
    Add-Content -Path $LogFile -Value "   --  $Text"
}

function Stop-WithError([string]$Text) {
    Write-Host ''
    Write-Host "   !!  $Text" -ForegroundColor Red
    Add-Content -Path $LogFile -Value "   !!  $Text"
    Write-Host "   Log: $LogFile"
    exit 1
}

# ----- Local settings ----------------------------------------------------------

$Settings = @{}
if (Test-Path $LocalConfig) {
    $Settings = Import-PowerShellDataFile -Path $LocalConfig
}

function Get-Setting([string]$Name) {
    if ($Settings.ContainsKey($Name) -and $Settings[$Name]) {
        return [string]$Settings[$Name]
    }
    return ''
}

# ----- .env --------------------------------------------------------------------

function Read-DotEnv([string]$Path) {
    $values = @{}
    foreach ($line in Get-Content -Path $Path) {
        if ($line -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$') {
            $value = $Matches[2].Trim()
            if ($value.Length -ge 2 -and $value.StartsWith('"') -and $value.EndsWith('"')) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            $values[$Matches[1]] = $value
        }
    }
    return $values
}

function Get-Env([hashtable]$Env, [string]$Name) {
    if ($Env.ContainsKey($Name)) {
        return [string]$Env[$Name]
    }
    return ''
}

# ----- Processes started by DORY -----------------------------------------------

function Read-State {
    if (-not (Test-Path $StateFile)) {
        return @()
    }
    try {
        $parsed = Get-Content -Raw -Path $StateFile | ConvertFrom-Json
        return @($parsed.services)
    } catch {
        return @()
    }
}

function Write-State([object[]]$Services, [hashtable]$Extra) {
    $state = [ordered]@{
        project = $Root
        updated = (Get-Date).ToString('o')
        services = $Services
    }
    foreach ($key in $Extra.Keys) {
        $state[$key] = $Extra[$key]
    }
    $state | ConvertTo-Json -Depth 5 | Set-Content -Path $StateFile -Encoding UTF8
}

# A recorded process is ours only if it is the same cmd.exe window we
# started: same id, same start time, and our window title in its command.
function Test-OurProcess($Service) {
    $process = Get-CimInstance Win32_Process -Filter ("ProcessId = " + [int]$Service.pid) -ErrorAction SilentlyContinue
    if ($null -eq $process) {
        return $false
    }
    if ($process.Name -ne 'cmd.exe') {
        return $false
    }
    if ($process.CommandLine -notlike ('*' + $Service.title + '*')) {
        return $false
    }
    $started = $process.CreationDate.ToUniversalTime().ToString('o')
    return $started -eq $Service.created
}

# The window stays open (cmd /k) after its program ends, for example a worker
# that lost its database connection. The service runs only while something
# still runs inside the window.
function Test-ServiceAlive($Service) {
    $children = @(Get-CimInstance Win32_Process -Filter ("ParentProcessId = " + [int]$Service.pid) -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne 'conhost.exe' })
    return $children.Count -gt 0
}

function Start-DoryWindow([string]$Title, [string]$WorkingDirectory, [string]$Command) {
    $arguments = '/k title ' + $Title + ' && cd /d "' + $WorkingDirectory + '" && ' + $Command
    $process = Start-Process -FilePath 'cmd.exe' -ArgumentList $arguments -PassThru -WindowStyle Normal
    Start-Sleep -Milliseconds 700
    $cim = Get-CimInstance Win32_Process -Filter ("ProcessId = " + $process.Id)
    return [ordered]@{
        name = $Title
        title = $Title
        pid = $process.Id
        created = $cim.CreationDate.ToUniversalTime().ToString('o')
        command = $Command
    }
}

function Get-PortOwner([int]$Port) {
    $connection = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $connection) {
        return $null
    }
    return Get-Process -Id $connection.OwningProcess -ErrorAction SilentlyContinue
}

function Test-TcpPort([string]$HostName, [int]$Port, [int]$TimeoutMs) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $task = $client.ConnectAsync($HostName, $Port)
        return $task.Wait($TimeoutMs) -and $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Wait-Until([scriptblock]$Check, [int]$Seconds) {
    $deadline = (Get-Date).AddSeconds($Seconds)
    while ((Get-Date) -lt $deadline) {
        if (& $Check) {
            return $true
        }
        Start-Sleep -Milliseconds 700
    }
    return $false
}

function Test-ApiHealth {
    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:$ApiPort/up" -UseBasicParsing -TimeoutSec 3
        return $response.StatusCode -eq 200
    } catch {
        return $false
    }
}

# Opens a real Pusher-protocol connection and waits for the server's
# welcome: proves Reverb runs and knows this application key.
function Test-ReverbHandshake([string]$Key) {
    $socket = New-Object System.Net.WebSockets.ClientWebSocket
    $cancel = New-Object System.Threading.CancellationTokenSource 5000
    try {
        $uri = [Uri]("ws://127.0.0.1:$ReverbPort/app/$Key" + '?protocol=7&client=dory-startup&version=1.0')
        $socket.ConnectAsync($uri, $cancel.Token).Wait()
        $buffer = New-Object byte[] 4096
        $segment = New-Object System.ArraySegment[byte] -ArgumentList (, $buffer)
        $result = $socket.ReceiveAsync($segment, $cancel.Token).Result
        $text = [System.Text.Encoding]::UTF8.GetString($buffer, 0, $result.Count)
        return $text -like '*pusher:connection_established*'
    } catch {
        return $false
    } finally {
        $socket.Dispose()
    }
}

# ----- Tools ---------------------------------------------------------------------

function Find-Php {
    $candidates = @()
    $configured = Get-Setting 'PhpPath'
    if ($configured) { $candidates += $configured }
    if ($env:DORY_PHP) { $candidates += $env:DORY_PHP }
    $onPath = Get-Command php -ErrorAction SilentlyContinue
    if ($onPath) { $candidates += $onPath.Source }

    foreach ($candidate in $candidates) {
        if (-not (Test-Path $candidate)) { continue }
        $version = & $candidate -r 'echo PHP_VERSION;' 2>$null
        if ($version -and ([version]($version -replace '[^0-9\.].*$', '')) -ge [version]'8.3') {
            return @{ Path = $candidate; Version = $version }
        }
        Write-Note "PHP $version at $candidate is too old (DORY needs 8.3 or newer)."
    }
    return $null
}

function Find-Adb {
    $candidates = @()
    $configured = Get-Setting 'AdbPath'
    if ($configured) { $candidates += $configured }
    foreach ($sdk in @($env:ANDROID_HOME, $env:ANDROID_SDK_ROOT, (Join-Path $env:LOCALAPPDATA 'Android\Sdk'))) {
        if ($sdk) { $candidates += (Join-Path $sdk 'platform-tools\adb.exe') }
    }
    $onPath = Get-Command adb -ErrorAction SilentlyContinue
    if ($onPath) { $candidates += $onPath.Source }
    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) { return $candidate }
    }
    return $null
}

# =================================================================================
Write-Host 'DORY local development startup' -ForegroundColor White
Write-Host "Project: $Root"
Add-Content -Path $LogFile -Value "DORY startup $(Get-Date -Format o) in $Root"

# ----- Already running? -------------------------------------------------------------

Write-Step 'Processes from an earlier start'
$running = @()
foreach ($service in Read-State) {
    if (-not (Test-OurProcess $service)) {
        continue
    }
    if (Test-ServiceAlive $service) {
        $running += $service
        Write-Note ("{0} is already running (window {1}, process {2})." -f $service.name, $service.title, $service.pid)
    } else {
        # Our own window (same id, title and start time), with nothing left in it.
        & taskkill.exe /PID ([int]$service.pid) /T /F | Out-Null
        Write-Note ("{0} had stopped; its window stayed open. Closed it: it is started again below." -f $service.title)
    }
}
if ($running.Count -eq 0) {
    Write-Ok 'None running.'
}
function Test-Running([string]$Title) {
    foreach ($service in $running) {
        if ($service.title -eq $Title) { return $true }
    }
    return $false
}

# ----- Pre-flight ---------------------------------------------------------------------

Write-Step 'Checking the tools'
$php = Find-Php
if ($null -eq $php) {
    Stop-WithError ("PHP 8.3 or newer was not found. Create scripts\dev\dory.local.psd1 from " +
        "dory.local.example.psd1 and set PhpPath (for example D:\php84\php.exe), or set DORY_PHP.")
}
Write-Ok ("PHP {0} ({1})" -f $php.Version, $php.Path)

$flutter = Get-Command flutter -ErrorAction SilentlyContinue
if (-not $NoFlutter -and $null -eq $flutter) {
    Stop-WithError 'Flutter was not found on PATH. Install Flutter or run with -NoFlutter.'
}
if ($flutter) { Write-Ok ("Flutter ({0})" -f $flutter.Source) }

$adb = $null
if (-not $NoFlutter) {
    $adb = Find-Adb
    if ($null -eq $adb) {
        Stop-WithError ('adb was not found. Install the Android SDK platform-tools, set ANDROID_HOME, ' +
            'or set AdbPath in scripts\dev\dory.local.psd1.')
    }
    Write-Ok "adb ($adb)"
}

Write-Step 'Checking the project'
$envFile = Join-Path $Backend '.env'
if (-not (Test-Path $envFile)) {
    Stop-WithError 'backend\.env is missing. Copy backend\.env.example to backend\.env and fill it in (docs/LOCAL_DEVELOPMENT_STARTUP.md).'
}
$dotEnv = Read-DotEnv $envFile
if (-not (Get-Env $dotEnv 'APP_KEY')) {
    Stop-WithError 'APP_KEY is empty in backend\.env. Run: php artisan key:generate (in backend).'
}
Write-Ok 'backend\.env with an APP_KEY'
if (-not (Test-Path (Join-Path $Backend 'vendor\autoload.php'))) {
    Stop-WithError 'Composer packages are missing. In backend, run: composer install'
}
Write-Ok 'Composer packages installed'
if (-not $NoFlutter -and -not (Test-Path (Join-Path $Root '.dart_tool\package_config.json'))) {
    Stop-WithError 'Flutter packages are missing. In the project folder, run: flutter pub get'
}
if (-not $NoFlutter) { Write-Ok 'Flutter packages installed' }

$missing = @()
foreach ($name in @('REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET')) {
    if (-not (Get-Env $dotEnv $name)) { $missing += $name }
}
if ($missing.Count -gt 0) {
    Stop-WithError ('Real-time is not configured: ' + ($missing -join ', ') +
        ' empty in backend\.env. See docs/REVERB_SETUP.md, "Local keys".')
}
if ((Get-Env $dotEnv 'BROADCAST_CONNECTION') -ne 'reverb') {
    Write-Note 'BROADCAST_CONNECTION is not "reverb": the API will not broadcast. See docs/REVERB_SETUP.md.'
} else {
    Write-Ok 'Broadcasting through Reverb'
}
if ((Get-Env $dotEnv 'QUEUE_CONNECTION') -eq 'sync') {
    Write-Note 'QUEUE_CONNECTION is "sync": broadcasts run inside each request. "database" is expected.'
}

Write-Step 'Checking the database configured in backend\.env'
$dbHost = Get-Env $dotEnv 'DB_HOST'
if (-not $dbHost) { $dbHost = '127.0.0.1' }
$dbPort = Get-Env $dotEnv 'DB_PORT'
if (-not $dbPort) { $dbPort = '3306' }
$dbName = Get-Env $dotEnv 'DB_DATABASE'
if (-not (Test-TcpPort $dbHost ([int]$dbPort) 2000)) {
    Stop-WithError ("Nothing answers on $dbHost`:$dbPort (DB_HOST, DB_PORT). If you use XAMPP, " +
        'open the XAMPP Control Panel and start MySQL. Nothing was changed.')
}
Push-Location $Backend
try {
    $status = & $php.Path artisan migrate:status --no-ansi 2>&1
    $statusExit = $LASTEXITCODE
} finally {
    Pop-Location
}
if ($statusExit -ne 0) {
    Stop-WithError ("The database server answers, but Laravel cannot use database '$dbName': " +
        (($status | Select-Object -Last 3) -join ' '))
}
Write-Ok "Connected to '$dbName' on $dbHost`:$dbPort"

$pending = @($status | Where-Object { $_ -match 'Pending' } | ForEach-Object { ($_ -replace '\.{2,}.*$', '').Trim() })
if ($pending.Count -gt 0) {
    Write-Note ("{0} migration(s) not run yet on '{1}':" -f $pending.Count, $dbName)
    foreach ($name in $pending) { Write-Host "         $name" }
    $appEnv = Get-Env $dotEnv 'APP_ENV'
    if ($appEnv -ne 'local' -or $dbName -like '*_testing' -or -not $dbName) {
        Stop-WithError ("Not running them: APP_ENV is '$appEnv' and the database is '$dbName'. " +
            'Migrations are run here only for the local development database.')
    }
    $answer = 'n'
    if ($AssumeYes) {
        $answer = 'y'
    } else {
        $answer = Read-Host ("   Run these pending migrations on '{0}' now? They only add to the schema. [y/N]" -f $dbName)
    }
    if ($answer -match '^(y|yes)$') {
        Push-Location $Backend
        try {
            & $php.Path artisan migrate --no-interaction --no-ansi 2>&1 | ForEach-Object { Write-Host "         $_" }
            if ($LASTEXITCODE -ne 0) { Stop-WithError 'The migrations failed: see above. Nothing else was started.' }
        } finally {
            Pop-Location
        }
        Write-Ok 'Pending migrations applied'
    } else {
        Write-Note 'Left as they are. Screens that need the new tables will fail until you run them.'
    }
} else {
    Write-Ok 'No pending migrations'
}

Write-Step 'Checking ports'
foreach ($entry in @(@{ Port = $ApiPort; Title = 'DORY-API' }, @{ Port = $ReverbPort; Title = 'DORY-REVERB' })) {
    if (Test-Running $entry.Title) {
        Write-Ok ("Port {0}: DORY's own {1}" -f $entry.Port, $entry.Title)
        continue
    }
    $owner = Get-PortOwner $entry.Port
    if ($null -ne $owner) {
        $message = 'Port {0} is used by {1} (process {2}), which this script did not start. ' +
            'Close it, or stop the earlier DORY start with stop_dory.bat. Nothing was stopped.'
        Stop-WithError ($message -f $entry.Port, $owner.ProcessName, $owner.Id)
    }
    Write-Ok ("Port {0} is free" -f $entry.Port)
}

# ----- Services --------------------------------------------------------------------------

$services = @($running)
$phpQuoted = '"' + $php.Path + '"'

Write-Step 'Starting the API (DORY-API)'
if (-not (Test-Running 'DORY-API')) {
    $services += Start-DoryWindow 'DORY-API' $Backend "$phpQuoted artisan serve --host=127.0.0.1 --port=$ApiPort"
    Write-State $services @{}
}
if (-not (Wait-Until { Test-ApiHealth } 40)) {
    Stop-WithError "The API did not answer on http://127.0.0.1:$ApiPort/up. Read the DORY-API window."
}
Write-Ok "http://127.0.0.1:$ApiPort/up answers"

Write-Step 'Starting Reverb (DORY-REVERB)'
if (-not (Test-Running 'DORY-REVERB')) {
    $services += Start-DoryWindow 'DORY-REVERB' $Backend "$phpQuoted artisan reverb:start --host=127.0.0.1 --port=$ReverbPort"
    Write-State $services @{}
}
$reverbKey = Get-Env $dotEnv 'REVERB_APP_KEY'
if (-not (Wait-Until { Test-ReverbHandshake $reverbKey } 40)) {
    Stop-WithError "Reverb did not accept a WebSocket connection on port $ReverbPort. Read the DORY-REVERB window."
}
Write-Ok "ws://127.0.0.1:$ReverbPort accepts connections for this app key"

Write-Step 'Starting the queue worker (DORY-QUEUE)'
if (-not (Test-Running 'DORY-QUEUE')) {
    $others = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
        $_.CommandLine -like '*queue:work*' -and $_.CommandLine -like '*artisan*'
    })
    if ($others.Count -gt 0) {
        Write-Note ('A queue worker is already running (process ' + ($others.ProcessId -join ', ') + '), started outside this script. Not starting a second one.')
    } else {
        $services += Start-DoryWindow 'DORY-QUEUE' $Backend "$phpQuoted artisan queue:work --queue=realtime,notifications,default --sleep=1 --tries=3 --timeout=90"
        Write-State $services @{}
        Write-Ok 'Worker started (queues: realtime, notifications, default)'
    }
} else {
    Write-Ok 'Already running'
}

# The scheduler: billing:sweep every 15 minutes (docs/SUBSCRIPTIONS.md). Plans
# are right without it; it stores trial ends, unpaid periods and expired
# payment links. Locally it never deletes anything (routes/console.php).
Write-Step 'Starting the scheduler (DORY-SCHEDULER)'
if (-not (Test-Running 'DORY-SCHEDULER')) {
    $others = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
        $_.CommandLine -like '*schedule:work*' -and $_.CommandLine -like '*artisan*'
    })
    if ($others.Count -gt 0) {
        Write-Note ('A scheduler is already running (process ' + ($others.ProcessId -join ', ') + '), started outside this script. Not starting a second one.')
    } else {
        $services += Start-DoryWindow 'DORY-SCHEDULER' $Backend "$phpQuoted artisan schedule:work"
        Write-State $services @{}
        Write-Ok 'Scheduler started (billing:sweep every 15 minutes)'
    }
} else {
    Write-Ok 'Already running'
}

# ----- Flutter -------------------------------------------------------------------------

$extra = @{}
if ($NoFlutter) {
    Write-Step 'Flutter'
    Write-Note 'Skipped (-NoFlutter).'
} else {
    Write-Step 'Finding the device'
    $lines = & $adb devices 2>$null | Select-Object -Skip 1 | Where-Object { $_.Trim() }
    $ready = @()
    foreach ($line in $lines) {
        $parts = $line -split '\s+'
        if ($parts.Count -lt 2) { continue }
        if ($parts[1] -eq 'unauthorized') {
            Write-Note ("Device {0} has not allowed USB debugging from this computer: unlock it and accept the prompt." -f $parts[0])
        } elseif ($parts[1] -eq 'device') {
            $ready += $parts[0]
        }
    }
    $serial = $Device
    if (-not $serial) {
        $phones = @($ready | Where-Object { $_ -notlike 'emulator-*' })
        $emulators = @($ready | Where-Object { $_ -like 'emulator-*' })
        if ($Emulator -and $emulators.Count -gt 0) { $serial = $emulators[0] }
        elseif ($phones.Count -eq 1) { $serial = $phones[0] }
        elseif ($phones.Count -gt 1) {
            Write-Note ('Several phones: ' + ($phones -join ', '))
            $serial = Read-Host '   Which one (device id)?'
        }
        elseif ($emulators.Count -gt 0) { $serial = $emulators[0] }
    }
    if (-not $serial) {
        Write-Note ('No Android device. Connect your phone with USB debugging on (Settings, Developer options), ' +
            'or start an emulator, then run start_dory.bat again: the API, Reverb and the worker keep running.')
        Write-Note 'Chrome is not available: this project has no web platform.'
    } else {
        $isEmulator = $serial -like 'emulator-*'
        if ($isEmulator) {
            # Inside the emulator, 10.0.2.2 is the computer; 127.0.0.1 is the emulator itself.
            $apiHost = '10.0.2.2'
            Write-Ok "Emulator $serial (computer reachable at 10.0.2.2)"
        } else {
            $apiHost = '127.0.0.1'
            foreach ($port in @($ApiPort, $ReverbPort)) {
                & $adb -s $serial reverse "tcp:$port" "tcp:$port" | Out-Null
            }
            $list = (& $adb -s $serial reverse --list) -join "`n"
            if ($list -notlike "*tcp:$ApiPort*" -or $list -notlike "*tcp:$ReverbPort*") {
                Stop-WithError "adb reverse did not take on $serial. Unplug and replug the phone, then try again."
            }
            Write-Ok "Phone $serial; ports $ApiPort and $ReverbPort forwarded with adb reverse"
            $extra['adb_reverse'] = @{ serial = $serial; ports = @($ApiPort, $ReverbPort) }
        }

        $fcm = 'false'
        $googleServices = Join-Path $Root 'android\app\google-services.json'
        if ((Get-Env $dotEnv 'FCM_ENABLED') -eq 'true' -and (Test-Path $googleServices)) {
            $fcm = 'true'
            Write-Ok 'Firebase Cloud Messaging enabled'
        } else {
            Write-Note ('Push notifications (FCM) are off: Firebase is not configured (docs/FIREBASE_SETUP.md). ' +
                'Reverb and the notification center work normally.')
        }

        Write-Step 'Starting Flutter (DORY-FLUTTER)'
        if (Test-Running 'DORY-FLUTTER') {
            Write-Ok 'Already running: press r (hot reload) or R (restart) in the DORY-FLUTTER window.'
        } else {
            $defines = @(
                "--dart-define=DORY_API_BASE_URL=http://$apiHost`:$ApiPort/api/v1",
                "--dart-define=DORY_REVERB_APP_KEY=$reverbKey",
                "--dart-define=DORY_REVERB_HOST=$apiHost",
                "--dart-define=DORY_REVERB_PORT=$ReverbPort",
                '--dart-define=DORY_REVERB_SCHEME=http',
                "--dart-define=DORY_FCM_ENABLED=$fcm"
            ) -join ' '
            $services += Start-DoryWindow 'DORY-FLUTTER' $Root "flutter run -d $serial $defines"
            Write-Ok "flutter run on $serial (first build takes a few minutes)"
        }
    }
}

Write-State $services $extra
Write-Host ''
Write-Host 'DORY is running.' -ForegroundColor Green
Write-Host "  API      http://127.0.0.1:$ApiPort/api/v1   (window DORY-API)"
Write-Host "  Reverb   ws://127.0.0.1:$ReverbPort            (window DORY-REVERB)"
Write-Host '  Worker   queues realtime, notifications, default (window DORY-QUEUE)'
Write-Host '  Schedule billing:sweep every 15 minutes (window DORY-SCHEDULER)'
Write-Host '  Stop     double-click stop_dory.bat'
Write-Host "  Log      $LogFile"
exit 0
