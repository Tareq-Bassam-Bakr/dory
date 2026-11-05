# Local Backend Setup

How to run the Laravel API (`backend/`) on a development machine. These steps
were verified on Windows 11 with XAMPP.

## Required software

| Software | Version | Notes |
|---|---|---|
| PHP | **8.3–8.5** (8.4.27 used) | Extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `xml`, `zip`, `sodium` |
| Composer | 2.x | |
| MySQL / MariaDB | MySQL 8.x (recommended) or MariaDB 10.3+ | XAMPP's MariaDB 10.4 works locally |
| Redis | optional locally | see [Redis](#redis) |

### PHP on Windows next to XAMPP

XAMPP bundles PHP 8.2, which is too old for Laravel 13. Phase 02 installed
the official PHP 8.4.27 (NTS x64, SHA-256 verified, from windows.php.net) in
**`D:\php84`**, with its own `php.ini`. XAMPP's Apache, MariaDB and PHP 8.2
are untouched.

`php` on your PATH still points to XAMPP's 8.2, so either:

- run commands with the full path: `D:\php84\php.exe artisan …` and
  `D:\php84\php.exe D:\composer\composer.phar …` (used below), or
- put `D:\php84` **before** `D:\xampp\php` in your user PATH (Windows
  Settings → Environment Variables), then reopen the terminal. After that,
  `php` and `composer` use 8.4.

Check:

```powershell
D:\php84\php.exe -v
```

## Database

Create the databases and a dedicated user. Use your own strong password and
put it **only** in `backend/.env`:

```sql
CREATE DATABASE dory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE dory_db_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dory_app'@'localhost' IDENTIFIED BY '<strong-password>';
CREATE USER 'dory_app'@'127.0.0.1' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON dory_db.* TO 'dory_app'@'localhost', 'dory_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON dory_db_testing.* TO 'dory_app'@'localhost', 'dory_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

On the Phase 02 machine this was done already; the generated password is in
`backend/.env`.

## First-time setup

```powershell
cd backend
```

```powershell
D:\php84\php.exe D:\composer\composer.phar install
```

```powershell
Copy-Item .env.example .env
```

Edit `.env`: set `DB_PASSWORD`, and set `DB_CONNECTION=mariadb` when using
XAMPP/MariaDB. Then:

```powershell
D:\php84\php.exe artisan key:generate
```

```powershell
D:\php84\php.exe artisan migrate
```

Seed the business categories (required; reference data, idempotent and
safe in every environment, including production with `--force`):

```powershell
D:\php84\php.exe artisan db:seed --class=BusinessCategorySeeder
```

Optional demo data: set `SEED_DEMO_PASSWORD` in `.env`, then (this also
seeds the categories):

```powershell
D:\php84\php.exe artisan db:seed
```

## Running

```powershell
D:\php84\php.exe artisan serve
```

The API is then at `http://127.0.0.1:8000/api/v1`. Quick check:

```powershell
curl.exe http://127.0.0.1:8000/api/v1/health
```

To reach the API from a **USB-connected phone**, forward the phone's port
8000 to the PC (repeat after reconnecting); the debug app then uses
`http://127.0.0.1:8000/api/v1` as is:

```powershell
adb reverse tcp:8000 tcp:8000
```

Over Wi-Fi instead, listen on all interfaces and build the app with
`--dart-define=DORY_API_BASE_URL=http://<PC LAN IP>:8000/api/v1`:

```powershell
D:\php84\php.exe artisan serve --host=0.0.0.0 --port=8000
```

Scheduled housekeeping (token pruning) when needed:

```powershell
D:\php84\php.exe artisan schedule:work
```

## Tests and quality checks

| Command | What it does |
|---|---|
| `D:\php84\php.exe D:\composer\composer.phar test` | Full suite on SQLite in-memory (fast, isolated) |
| `D:\php84\php.exe D:\composer\composer.phar test:mariadb` | Same suite on MariaDB `dory_db_testing` |
| `D:\php84\php.exe D:\composer\composer.phar test:mysql` | Same suite on MySQL `dory_db_testing` |
| `D:\php84\php.exe D:\composer\composer.phar lint` | Pint check (no changes) |
| `D:\php84\php.exe D:\composer\composer.phar format` | Pint auto-format |
| `D:\php84\php.exe D:\composer\composer.phar audit` | Known-vulnerability check of dependencies |
| `D:\php84\php.exe artisan route:list --path=api` | List API routes |

The test databases are wiped by the tests. Never point them at `dory_db`.

## Redis

Redis is configured entirely from the environment (`REDIS_CLIENT`,
`REDIS_HOST`, `REDIS_PORT`, `REDIS_USERNAME`, `REDIS_PASSWORD`, or
`REDIS_URL` for TLS). Production uses Redis for cache and queues.

**Status on the Phase 02 machine: Redis is not installed.** Nothing listens
on port 6379, and there is no PHP Redis client. The documented fallback is
active:

```ini
CACHE_STORE=database
QUEUE_CONNECTION=database
```

The health endpoint then reports `"redis": "not_used"`. It reports `fail`
(HTTP 503) if Redis is configured but unreachable. This is covered by a test.

### Installing Redis locally (Windows)

Pick one:

1. **Memurai Developer Edition**, a Redis-compatible Windows service, free
   for development: `winget install Memurai.MemuraiDeveloper`.
2. **Redis in WSL2** (Ubuntu): `sudo apt install redis-server`. Needs WSL
   installed and about 1 GB more RAM.
3. **Docker**: `docker run -p 6379:6379 redis:7`. Heavy on 8 GB machines.

Then give PHP a Redis client:

- **phpredis extension** (recommended, same as production): place a
  `php_redis.dll` matching PHP 8.4 NTS x64 in `D:\php84\ext` and add
  `extension=redis` to `D:\php84\php.ini`; or
- **predis** (pure PHP, local fallback): `composer require predis/predis`
  and set `REDIS_CLIENT=predis`.

Finally set `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis`, run
`php artisan config:clear`, and check `/api/v1/health` reports
`"redis": "ok"`.

## Mail

`MAIL_MAILER=log`: emails, such as password reset links, are written to
`storage/logs/mail-*.log`, and **nothing is delivered**. To send real email,
configure an SMTP or API provider in `.env` (`MAIL_MAILER`, `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`).

Team invitations are never sent through the `log` mailer (the link
contains a secret token). Locally the API returns the invitation link to
the owner for manual sharing (`INVITATION_SHARE_LINKS`, on outside
production). With a real provider, set `INVITATION_SEND_EMAILS=true`. Other
settings: `INVITATION_URL` (default `dory://app/invitations/{token}`) and
`INVITATION_EXPIRES_IN_DAYS` (default 7). See
[MEMBERSHIP_AND_INVITATIONS.md](MEMBERSHIP_AND_INVITATIONS.md).

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Composer detected issues in your platform: … >= 8.3.0` | You ran XAMPP's PHP 8.2. Use `D:\php84\php.exe`. |
| `Warning: Module "openssl" is already loaded` | XAMPP's `php.ini` loads openssl twice (lines 942/984). Harmless; comment out one line. Not present with `D:\php84`. |
| Composer download "Permission denied" / connection reset | Antivirus locking or a flaky network. Retry with `$env:COMPOSER_MAX_PARALLEL_HTTP=3`. |
| `SQLSTATE[HY000] [1045] Access denied` | Check `DB_USERNAME`/`DB_PASSWORD` in `.env`, then `php artisan config:clear`. |
| 401 on every request | Missing `Authorization: Bearer …` header, or the token expired/was revoked. |
