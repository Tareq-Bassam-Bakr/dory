# DORY

**Your Turn. Your Time.**

DORY is a smart queue and appointment management platform. Customers discover
nearby businesses, join virtual queues, and follow their turn live. Businesses
manage branches, services, queues, and staff from the same app.

This repository contains both parts of the product:

| Part | Location | Stack |
|---|---|---|
| Mobile app | repository root (`lib/`, `android/`, `ios/`, …) | Flutter 3.44, Riverpod 3, GoRouter 18 |
| API | [`backend/`](backend) | Laravel 13, PHP 8.4, MySQL, Sanctum |

> **Status: Phase 02 complete.** The Flutter app has its architecture, design
> system, Arabic/English localization and UI shells (Phase 01). The Laravel API
> provides authentication, the database foundation and multi-tenant
> authorization (Phase 02). **The app is not connected to the API yet**, and
> there is no queue functionality yet. Both arrive in later phases.

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
