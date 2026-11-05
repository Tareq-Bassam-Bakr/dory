# DORY

**Your Turn. Your Time.**

DORY is a smart queue and appointment management platform. Customers discover
nearby businesses, join virtual queues, and follow their turn live. Businesses
manage branches, services, queues, and staff from the same app.

This repository contains both parts of the product:

| Part | Location | Stack |
|---|---|---|
| Mobile app | repository root (`lib/`, `android/`, `ios/`, …) | Flutter 3.44, Riverpod 3, GoRouter 18 |
| API | [`backend/`](backend) | Laravel 13, PHP 8.4, MySQL/MariaDB, Sanctum |

> **Status: Phase 03 complete.** The app signs users in with email and
> password against the Laravel API, keeps the session securely across
> restarts, and lets businesses onboard and manage their profile, branches,
> services and team (with invitations). Categories come from the database.
> Tenant isolation is enforced by the API and covered by tests. **There is no
> queue functionality yet** (later phase).

## Required software

| Software | Version | Used by |
|---|---|---|
| Flutter / Dart | 3.44+ / 3.12+ (stable) | mobile app |
| Android SDK | 36 (minSdk 24) | mobile app |
| PHP | 8.3–8.5 (8.4.27 used) | API |
| Composer | 2.x | API |
| MySQL / MariaDB | MySQL 8.x (production) or MariaDB 10.3+ (local) | API |
| Redis | optional locally, required in production | API |

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

### Run the app against the local API

Start the API (see below), connect the phone by USB, then:

```powershell
adb reverse tcp:8000 tcp:8000
```

```bash
flutter run -d <device-id>
```

Debug builds call `http://127.0.0.1:8000/api/v1`; other targets use
`--dart-define=DORY_API_BASE_URL=...` (required for release builds). The
on-device end-to-end test needs the running API:

```bash
flutter test integration_test/phase03_e2e_test.dart -d <device-id>
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

Run the tests (SQLite in-memory, isolated):

```powershell
D:\php84\php.exe D:\composer\composer.phar test
```

Run the same tests on MariaDB (`dory_db_testing`):

```powershell
D:\php84\php.exe D:\composer\composer.phar test:mariadb
```

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
