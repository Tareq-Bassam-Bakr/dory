# Local development startup

Double-click **`start_dory.bat`** in the repository root. It checks
everything, then opens one window per service:

| Window | Runs |
|---|---|
| DORY-API | `php artisan serve` on <http://127.0.0.1:8000> |
| DORY-REVERB | `php artisan reverb:start` on `ws://127.0.0.1:8080` |
| DORY-QUEUE | `php artisan queue:work --queue=realtime,notifications,default` |
| DORY-FLUTTER | `flutter run` on the connected phone or emulator |

To stop, double-click **`stop_dory.bat`**. It stops exactly these windows
and nothing else.

The logic lives in `scripts/dev/start_dory.ps1` and `stop_dory.ps1`. The
`.bat` files only call them with `-ExecutionPolicy Bypass` for that one run,
so no system setting changes.

## Before the first start

1. **XAMPP:** start MySQL (MariaDB) in the XAMPP Control Panel. The script
   never starts, stops or changes XAMPP.
2. **PHP 8.3+:** XAMPP's PHP 8.2 is too old. Copy
   `scripts/dev/dory.local.example.psd1` to `scripts/dev/dory.local.psd1`
   (ignored by Git) and set `PhpPath`, for example `D:\php84\php.exe`.
   Alternatives: the `DORY_PHP` environment variable, or a PHP 8.3+ on
   PATH.
3. **`backend/.env`:** with `APP_KEY`, the database, and the Reverb keys
   ([REVERB_SETUP.md](REVERB_SETUP.md#local-keys)).
4. **Packages:** `composer install` in `backend`, and `flutter pub get` in
   the root.
5. **Phone:** USB debugging on, and the computer allowed on the phone.

## What the start checks, in order

1. **An earlier start:** services still running from it are reused, never
   started twice. A window whose program has stopped is closed and started
   again. The window stays open after its program ends, for example a
   worker that lost its database connection while the computer slept.
2. **Tools:** PHP 8.3+, Flutter, adb.
3. **Project:**
   - `backend/.env` with `APP_KEY`;
   - `vendor` and `.dart_tool` present;
   - the Reverb keys set;
   - `BROADCAST_CONNECTION=reverb`.
4. **Database:** the server in `DB_HOST`/`DB_PORT` answers, and
   `artisan migrate:status` works.
   - **Pending migrations are listed and you are asked** `[y/N]`. They run
     only on a yes, and only when `APP_ENV=local` and the database is not a
     `_testing` one.
   - Anything else leaves the database untouched. The script never runs
     `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` or
     `db:seed`, and never deletes records.
5. **Ports 8000 and 8080:** a port held by a program this script did not
   start stops the start with its name and process id. Nothing is killed.
6. **API:** `/up` must answer.
7. **Reverb:** a real WebSocket handshake with the app key must answer
   `pusher:connection_established`.
8. **Worker:** if a `queue:work` started outside the script is already
   running, no second one is started.
9. **Device:**
   - **One USB phone:** `adb reverse` for 8000 and 8080, checked with
     `adb reverse --list`. The app uses `127.0.0.1`.
   - **Emulator** (or `-Emulator`): the app uses `10.0.2.2`.
   - **Several phones:** asks which one.
   - **No device:** the backend keeps running; connect one and start
     again. There is no Chrome target, since the project has no web
     platform.
10. **Flutter** with:
    - `DORY_API_BASE_URL`;
    - `DORY_REVERB_APP_KEY` (the public key only, never the secret);
    - `DORY_REVERB_HOST`, `DORY_REVERB_PORT`, `DORY_REVERB_SCHEME=http`;
    - `DORY_FCM_ENABLED`: `true` only with `FCM_ENABLED=true` and
      `android/app/google-services.json`.

Every step prints OK, a note (`--`) or a clear error (`!!`), with the next
step to take. Everything is also written to `.dory/logs/start-*.log`.

## Options (from a terminal)

```bat
start_dory.bat -NoFlutter        :: backend only
start_dory.bat -Emulator         :: prefer the emulator
start_dory.bat -Device R58N123   :: a specific device id
start_dory.bat -AssumeYes        :: answer yes to the migrations question
```

`set DORY_NO_PAUSE=1` skips the final "Press any key" (for scripts).

## How the stop knows what is DORY's

The start records each window in `.dory/run/processes.json`: its process
id, its title and its exact start time. `stop_dory.bat` stops a window
(with what runs in it) only if all three still match. A reused process id
belongs to another program and is left alone. XAMPP, MariaDB, the MySQL test
server, Chrome, adb and other PHP processes are never touched. The `adb
reverse` rules the start added are removed; the adb server keeps running.

## Daily use

- Code changes in Flutter: press `r` (hot reload) or `R` (restart) in
  DORY-FLUTTER. Starting again does not open a second Flutter window.
- Backend changes: PHP files apply on the next request. After changing
  queued jobs, events or `.env`, run `stop_dory.bat` then `start_dory.bat`,
  because the worker and Reverb keep the old code.
- Watch real-time traffic: stop, then run Reverb by hand with `--debug`
  ([REVERB_SETUP.md](REVERB_SETUP.md#running)).

## Troubleshooting

| Message | What to do |
|---|---|
| PHP 8.3 or newer was not found | set `PhpPath` in `scripts/dev/dory.local.psd1` |
| Nothing answers on 127.0.0.1:3306 | start MySQL in XAMPP |
| Port 8000 is used by … | close that program, or `stop_dory.bat` if it is an earlier DORY |
| Real-time is not configured | Reverb keys in `backend/.env` ([REVERB_SETUP.md](REVERB_SETUP.md#local-keys)) |
| Device … has not allowed USB debugging | unlock the phone and accept the prompt |
| adb reverse did not take | unplug and replug the phone |
| Screens say "every 15 seconds" instead of "Live" / "Updates arrive as soon as the business acts" | the app has no Reverb connection: is DORY-REVERB running, and was the app started by `start_dory.bat` (it passes the Reverb key)? |
| "Live", but changes arrive only when you pull to refresh | DORY-QUEUE is not running: broadcasts wait in the queue. Run `start_dory.bat` again: it restarts a stopped worker |
| The first screens take long or say DORY cannot be reached, right after the app was built | the PHP development server is slow on its first requests after a build. Wait a moment and retry |
