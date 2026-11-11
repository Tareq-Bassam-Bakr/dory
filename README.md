# DORY

**Your Turn. Your Time.**

DORY is a smart queue and appointment management platform. Customers discover
nearby businesses, join virtual queues, and follow their turn. Businesses
manage branches, services, queues, and staff from the same app.

This repository contains both parts of the product:

| Part | Location | Stack |
|---|---|---|
| Mobile app | repository root (`lib/`, `android/`, `ios/`, …) | Flutter 3.44, Riverpod 3, GoRouter 18 |
| API | [`backend/`](backend) | Laravel 13, PHP 8.4, MySQL/MariaDB, Sanctum |

> **Status: Phase 08 complete.** Businesses have plans (Free, Pro, Business)
> that limit what they can add (branches, queues, team seats) and unlock
> insights, enforced by the server. Customers never pay. Plans, prices and
> limits live in the database and are managed through an admin API. Payments
> received by transfer, cash or mobile wallet are recorded by an admin; online
> card payment through Mastercard Gateway is built but **not live**: it needs a
> bank merchant account ([docs/SUBSCRIPTIONS.md](docs/SUBSCRIPTIONS.md),
> [docs/PAYMENTS.md](docs/PAYMENTS.md)).
>
> Phase 07 delivered intelligence: wait estimates use what is happening at the
> counter, owners and managers see peak times and recommendations from their
> own records. These are statistics and explicit rules, with no external AI
> service ([docs/AI_ARCHITECTURE.md](docs/AI_ARCHITECTURE.md)).
>
> Phase 06 delivered real-time: queue changes reach tickets and staff
> screens at once through Laravel Reverb (self-hosted WebSockets), with the
> 15-second polling kept as the fallback. Signed-in users have a notification
> center with preferences; queue alerts (your turn, almost your turn,
> skipped, paused, new customer, …) are stored, shown live while the app is
> open, and prepared for push. One double-click starts everything locally:
> `start_dory.bat` ([docs/LOCAL_DEVELOPMENT_STARTUP.md](docs/LOCAL_DEVELOPMENT_STARTUP.md)).
> Phase 05 delivered discovery, guest and signed-in tickets, favorites and
> ratings; Phases 03 and 04 the business workspace and queue engine.
>
> **Not yet:** push notifications are implemented but **off**: no Firebase
> project is configured, so nothing reaches a closed app
> ([docs/FIREBASE_SETUP.md](docs/FIREBASE_SETUP.md)). iOS push is not set up.
> Linking a guest ticket to an account does not exist. A production map needs
> its own tile provider ([docs/OSM_TILE_PROVIDER.md](docs/OSM_TILE_PROVIDER.md)).
> GitHub Actions does not start for this repository, so MySQL 8.4 is verified
> on a local server only ([docs/MYSQL_84_COMPATIBILITY.md](docs/MYSQL_84_COMPATIBILITY.md)).

## Required software

| Software | Version | Used by |
|---|---|---|
| Flutter / Dart | 3.44+ / 3.12+ (stable) | mobile app |
| Android SDK | 36 (minSdk 24) | mobile app |
| PHP | 8.3–8.5 (8.4.27 used) | API |
| Composer | 2.x | API |
| MySQL / MariaDB | MySQL 8.x (production) or MariaDB 10.3+ (local) | API |
| Redis | not needed locally; needed to run several Reverb servers | API |

## Mobile app (Flutter)

```bash
flutter pub get
```

```bash
flutter run
```

```bash
flutter analyze
```

```bash
flutter test
```

Details: [docs/DEVELOPMENT_GUIDE.md](docs/DEVELOPMENT_GUIDE.md).

### Start everything with one double-click

Double-click **`start_dory.bat`** in the repository root. It checks the
tools, `backend/.env`, the database (and asks before running pending
migrations), and the ports, then opens the windows DORY-API, DORY-REVERB,
DORY-QUEUE and DORY-FLUTTER and runs the app on the connected phone.
**`stop_dory.bat`** stops exactly those windows. XAMPP, MariaDB and other
programs are never touched, and no database is ever reset or seeded. Details:
[docs/LOCAL_DEVELOPMENT_STARTUP.md](docs/LOCAL_DEVELOPMENT_STARTUP.md).

### Run the app against the local API by hand

Start the API (see below), connect the phone by USB, then:

```powershell
adb reverse tcp:8000 tcp:8000
```

```bash
flutter run -d <device-id>
```

Debug builds call `http://127.0.0.1:8000/api/v1`; other targets use
`--dart-define=DORY_API_BASE_URL=...` (required for release builds). The
on-device tests (app launch, the business journey, the customer journey) need
the running API and, for the customer journey, a queue that accepts guests
([docs/CUSTOMER_EXPERIENCE.md](docs/CUSTOMER_EXPERIENCE.md#testing-on-a-device)):

```bash
flutter test integration_test -d <device-id>
```

Details: [docs/FLUTTER_API_INTEGRATION.md](docs/FLUTTER_API_INTEGRATION.md).

## API (Laravel)

Full guide, including database creation and Redis: [docs/LOCAL_BACKEND_SETUP.md](docs/LOCAL_BACKEND_SETUP.md).
On the Phase 02 Windows machine, PHP 8.4 lives in `D:\php84` (XAMPP's `php`
is 8.2), so the commands below use full paths. If PHP 8.3+ and Composer are
on your PATH, use `php` and `composer` directly.

Setup:

```powershell
cd backend
```

```powershell
D:\php84\php.exe D:\composer\composer.phar install
```

```powershell
Copy-Item .env.example .env
```

Set `DB_*` in `backend/.env` (database `dory_db`; use `DB_CONNECTION=mariadb`
for XAMPP), then:

```powershell
D:\php84\php.exe artisan key:generate
```

```powershell
D:\php84\php.exe artisan migrate
```

Seed the business categories (reference data, safe in every environment):

```powershell
D:\php84\php.exe artisan db:seed --class=BusinessCategorySeeder
```

Run the API at `http://127.0.0.1:8000/api/v1`:

```powershell
D:\php84\php.exe artisan serve
```

Real-time needs Reverb and a queue worker too (`start_dory.bat` starts all
three; keys: [docs/REVERB_SETUP.md](docs/REVERB_SETUP.md#local-keys)):

```powershell
D:\php84\php.exe artisan reverb:start
```

```powershell
D:\php84\php.exe artisan queue:work --queue=realtime,notifications,default
```

Run the tests (SQLite in-memory, isolated):

```powershell
D:\php84\php.exe D:\composer\composer.phar test
```

Run the same tests on MariaDB (`dory_db_testing`):

```powershell
D:\php84\php.exe D:\composer\composer.phar test:mariadb
```

Run the multi-process concurrency tests alone (MariaDB/MySQL only; they
are skipped on SQLite):

```powershell
$env:DB_CONNECTION='mariadb'; $env:DB_DATABASE='dory_db_testing'; D:\php84\php.exe artisan test tests/Feature/Concurrency
```

Guests join a queue at `http://127.0.0.1:8000/join/<token>` (the link and QR
code are on each queue's **QR code & link** screen). To run the suite on
the local MySQL 8.4 test server, see
[docs/MYSQL_84_COMPATIBILITY.md](docs/MYSQL_84_COMPATIBILITY.md#the-local-test-server).
The CI workflow runs it on SQLite and MySQL 8.4 once GitHub Actions starts for
this repository: [.github/workflows/backend-tests.yml](.github/workflows/backend-tests.yml).

Check code style:

```powershell
D:\php84\php.exe D:\composer\composer.phar lint
```

## Documentation

**Product and mobile app**

| Document | What it covers |
|---|---|
| [PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md) | Product vision, roles, phases |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Flutter layers, state management, routing, decisions |
| [FOLDER_STRUCTURE.md](docs/FOLDER_STRUCTURE.md) | Where Flutter code lives |
| [DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md) | Colors, typography, spacing, components |
| [LOCALIZATION.md](docs/LOCALIZATION.md) | Arabic/English, RTL, adding strings |
| [DEVELOPMENT_GUIDE.md](docs/DEVELOPMENT_GUIDE.md) | Flutter setup, commands, testing, dependencies |

**API**

| Document | What it covers |
|---|---|
| [BACKEND_ARCHITECTURE.md](docs/BACKEND_ARCHITECTURE.md) | Laravel structure, request lifecycle, rules, decisions |
| [DATABASE_FOUNDATION.md](docs/DATABASE_FOUNDATION.md) | Tables, keys, constraints, compatibility |
| [API_AUTHENTICATION.md](docs/API_AUTHENTICATION.md) | Tokens and every auth/profile endpoint |
| [API_CONVENTIONS.md](docs/API_CONVENTIONS.md) | Versioning, envelope, error codes, pagination, health |
| [MULTI_TENANCY.md](docs/MULTI_TENANCY.md) | Tenant isolation rules and building blocks |
| [SECURITY.md](docs/SECURITY.md) | Security controls and production checklist |
| [LOCAL_BACKEND_SETUP.md](docs/LOCAL_BACKEND_SETUP.md) | Installing and running the API locally |
| [PHASE_02_REPORT.md](docs/PHASE_02_REPORT.md) | What Phase 02 delivered, test results, open items |
| [API_ENDPOINTS_PHASE_03.md](docs/API_ENDPOINTS_PHASE_03.md) | Every Phase 03 endpoint with examples and error codes |
| [BUSINESS_MANAGEMENT.md](docs/BUSINESS_MANAGEMENT.md) | Businesses, categories, lifecycle, ownership, audit trail |
| [BUSINESS_ONBOARDING.md](docs/BUSINESS_ONBOARDING.md) | The five-step onboarding flow |
| [BRANCH_MANAGEMENT.md](docs/BRANCH_MANAGEMENT.md) | Branches, OpenStreetMap coordinates, branch access |
| [SERVICE_MANAGEMENT.md](docs/SERVICE_MANAGEMENT.md) | The generic service model |
| [MEMBERSHIP_AND_INVITATIONS.md](docs/MEMBERSHIP_AND_INVITATIONS.md) | Roles, ownership protection, invitations and delivery |
| [FLUTTER_API_INTEGRATION.md](docs/FLUTTER_API_INTEGRATION.md) | API client, secure session, guards, running against the API |
| [PHASE_03_REPORT.md](docs/PHASE_03_REPORT.md) | What Phase 03 delivered, test results, open items |

**Queue engine (Phase 04)**

| Document | What it covers |
|---|---|
| [QUEUE_ENGINE_ARCHITECTURE.md](docs/QUEUE_ENGINE_ARCHITECTURE.md) | Concepts, layers, real-time and priority readiness |
| [QUEUE_DATABASE_SCHEMA.md](docs/QUEUE_DATABASE_SCHEMA.md) | Queue tables, keys and invariants |
| [QUEUE_LIFECYCLE.md](docs/QUEUE_LIFECYCLE.md) | Configuration, sessions, closing policy, day roll-over |
| [TICKET_STATE_MACHINE.md](docs/TICKET_STATE_MACHINE.md) | Ticket states, transitions and who may trigger them |
| [TICKET_NUMBERING.md](docs/TICKET_NUMBERING.md) | `PREFIX-001`, daily reset, atomic allocation |
| [GUEST_JOINING.md](docs/GUEST_JOINING.md) | Public link, QR code, guest web page, access tokens |
| [QUEUE_SECURITY.md](docs/QUEUE_SECURITY.md) | Permissions, secrets, privacy, rate limits |
| [CONCURRENCY_STRATEGY.md](docs/CONCURRENCY_STRATEGY.md) | Locks, unique indexes, multi-process tests |
| [WAIT_TIME_ESTIMATION.md](docs/WAIT_TIME_ESTIMATION.md) | The estimate formula and its limits |
| [QUEUE_API_REFERENCE.md](docs/QUEUE_API_REFERENCE.md) | Every queue endpoint and error code |
| [MYSQL_84_COMPATIBILITY.md](docs/MYSQL_84_COMPATIBILITY.md) | Database test status, including MySQL 8.4 |
| [PHASE_04_REPORT.md](docs/PHASE_04_REPORT.md) | What Phase 04 delivered, test results, open items |

Customer experience (Phase 05):

| Document | Contents |
|---|---|
| [CUSTOMER_EXPERIENCE.md](docs/CUSTOMER_EXPERIENCE.md) | Navigation, screens, what visitors and customers can do |
| [BUSINESS_DISCOVERY.md](docs/BUSINESS_DISCOVERY.md) | What is public, live availability, efficiency |
| [SEARCH_AND_FILTERS.md](docs/SEARCH_AND_FILTERS.md) | Search parameters, sorting, limits |
| [MAPS_AND_LOCATION.md](docs/MAPS_AND_LOCATION.md) | Location rules, distance, the map |
| [OSM_TILE_PROVIDER.md](docs/OSM_TILE_PROVIDER.md) | Tile usage rules and configuring a production provider |
| [CUSTOMER_TICKET_EXPERIENCE.md](docs/CUSTOMER_TICKET_EXPERIENCE.md) | Joining, the digital ticket, polling, ratings |
| [GUEST_TICKET_PERSISTENCE.md](docs/GUEST_TICKET_PERSISTENCE.md) | How guest tickets and tokens are stored |
| [CUSTOMER_FAVORITES.md](docs/CUSTOMER_FAVORITES.md) | Saved businesses, on the server and on the device |
| [CUSTOMER_API_REFERENCE.md](docs/CUSTOMER_API_REFERENCE.md) | Every customer endpoint and error code |
| [CUSTOMER_SECURITY.md](docs/CUSTOMER_SECURITY.md) | Controls, their tests, and findings |
| [PHASE_05_REPORT.md](docs/PHASE_05_REPORT.md) | What Phase 05 delivered, test results, open items |

Real-time and notifications (Phase 06):

| Document | Contents |
|---|---|
| [REALTIME_ARCHITECTURE.md](docs/REALTIME_ARCHITECTURE.md) | From a queue change to the screen; live and polling modes |
| [REVERB_SETUP.md](docs/REVERB_SETUP.md) | Reverb configuration, local keys, running, production |
| [BROADCAST_CHANNELS.md](docs/BROADCAST_CHANNELS.md) | Channels, who may join them, event payloads |
| [NOTIFICATION_EVENTS.md](docs/NOTIFICATION_EVENTS.md) | Which queue changes notify whom, once |
| [NOTIFICATION_CENTER.md](docs/NOTIFICATION_CENTER.md) | Stored notifications, preferences, the screens |
| [PUSH_NOTIFICATIONS.md](docs/PUSH_NOTIFICATIONS.md) | FCM sending, payload, app states, current status |
| [FIREBASE_SETUP.md](docs/FIREBASE_SETUP.md) | Turning push on with your own Firebase project |
| [DEVICE_TOKEN_SECURITY.md](docs/DEVICE_TOKEN_SECURITY.md) | How push tokens are stored, registered and revoked |
| [LOCAL_DEVELOPMENT_STARTUP.md](docs/LOCAL_DEVELOPMENT_STARTUP.md) | `start_dory.bat` and `stop_dory.bat` |
| [PHASE_06_REPORT.md](docs/PHASE_06_REPORT.md) | What Phase 06 delivered, test results, open items |

AI intelligence (Phase 07):

| Document | Contents |
|---|---|
| [AI_ARCHITECTURE.md](docs/AI_ARCHITECTURE.md) | Wait prediction, peak times, insights, adding a language model later |
| [WAIT_TIME_ESTIMATION.md](docs/WAIT_TIME_ESTIMATION.md) | The estimate, its range and confidence |
| [PHASE_07_REPORT.md](docs/PHASE_07_REPORT.md) | What Phase 07 delivered, test results, limitations |

Subscriptions and payments (Phase 08):

| Document | Contents |
|---|---|
| [SUBSCRIPTIONS.md](docs/SUBSCRIPTIONS.md) | Plans and why these limits, lifecycle, entitlements, business and admin API |
| [PAYMENTS.md](docs/PAYMENTS.md) | Provider evaluation, how a payment is verified, webhooks, security, setting up Mastercard Gateway |
| [PHASE_08_REPORT.md](docs/PHASE_08_REPORT.md) | What Phase 08 delivered, test results, decisions and actions for the owner |
