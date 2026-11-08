# Laravel Reverb setup

DORY uses [Laravel Reverb](https://laravel.com/docs/13.x/broadcasting) as its
own WebSocket server, which costs nothing to run. Package `laravel/reverb`
1.12, configured by hand rather than with `install:broadcasting`, which
would also install JavaScript packages DORY does not use.

## What was added

| File | Purpose |
|---|---|
| `config/broadcasting.php`, `config/reverb.php` | Published. Reverb reads everything from `.env`; client events are off and allowed origins are configurable |
| `routes/channels.php` | Private channel rules ([BROADCAST_CHANNELS.md](BROADCAST_CHANNELS.md)) |
| `bootstrap/app.php` | `withBroadcasting`: `POST /api/v1/broadcasting/auth` with `auth:sanctum` and `active` |
| `config/queue.php` | Database queue: `after_commit => true`, `retry_after` 120 |

Reverb 1.12 requires `guzzlehttp/psr7` 2.x, so Guzzle moved from 8.3 to
7.16, which Laravel 13 accepts. Nothing in DORY used Guzzle directly.

## Environment (`backend/.env`)

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=          # your own values: see "Local keys"
REVERB_APP_KEY=         # public: the app needs it
REVERB_APP_SECRET=      # secret: never leaves the server, never committed
REVERB_HOST=127.0.0.1   # where Laravel sends broadcasts
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1   # where Reverb listens
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=*       # browsers only; mobile apps send no origin
REVERB_SCALING_ENABLED=false   # single server: no Redis
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=120
```

### Local keys

Generate random values once:

```powershell
D:\php84\php.exe -r "echo 'REVERB_APP_ID='.random_int(100000,999999).PHP_EOL.'REVERB_APP_KEY='.bin2hex(random_bytes(10)).PHP_EOL.'REVERB_APP_SECRET='.bin2hex(random_bytes(16)).PHP_EOL;"
```

Paste the three lines into `backend/.env`. The development machine already
has its own.

## Running

`start_dory.bat` does all of this ([LOCAL_DEVELOPMENT_STARTUP.md](LOCAL_DEVELOPMENT_STARTUP.md)).
By hand, in `backend`:

```powershell
D:\php84\php.exe artisan serve --host=127.0.0.1 --port=8000
D:\php84\php.exe artisan reverb:start --host=127.0.0.1 --port=8080
D:\php84\php.exe artisan queue:work --queue=realtime,notifications,default --sleep=1 --tries=3 --timeout=90
```

Add `--debug` to `reverb:start` to see every connection and message.
Broadcasts are queued: **without a worker, nothing reaches Reverb**. The app
then keeps polling, so it still works.

## The app side

Build defines: `DORY_REVERB_APP_KEY` (required to enable real-time),
`DORY_REVERB_HOST`, `DORY_REVERB_PORT`, and `DORY_REVERB_SCHEME`
(`http`/`https`). Debug builds default to `127.0.0.1:8080`. Release builds
need a host and `https` (WSS).

- **USB phone:** `adb reverse tcp:8080 tcp:8080`.
- **Emulator:** host `10.0.2.2`.

## Security

- Private channels are signed only after the channel rule accepted the
  authenticated user (Sanctum token, active account).
- `REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM=none` (the default here): clients
  cannot send events to each other. With Reverb's default `members`, anyone
  on the public guest channel could message everyone on it.
- `allowed_origins` restricts **browsers**. Mobile apps send no `Origin`
  header, so a restricted list would refuse the app. The protection of
  private data is the signed authorization, not the origin.

## Production

- Run Reverb behind TLS (`wss`), for example a reverse proxy on 443 to
  Reverb on 8080, with `REVERB_HOST` set to the public host,
  `REVERB_SCHEME=https` and `REVERB_PORT=443`.
- Keep Reverb, the worker and the API running with a process manager
  (Supervisor, systemd), with one worker per queue at minimum.
- Several Reverb servers need Redis: `REVERB_SCALING_ENABLED=true` and
  `REDIS_*`. Move `QUEUE_CONNECTION` and `CACHE_STORE` to `redis` at the
  same time.
- Raise the open file limit for many connections (Reverb's docs, "Running
  in production").
