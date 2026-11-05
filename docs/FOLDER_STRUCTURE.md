# Folder Structure

```
dory_app/
├── lib/
│   ├── main.dart                  # Entry point → bootstrap()
│   ├── bootstrap.dart             # Platform init, error handlers, DI overrides
│   ├── app/
│   │   ├── app.dart               # DoryApp: MaterialApp.router (theme, locale, router)
│   │   ├── router/
│   │   │   ├── app_routes.dart    # Route location constants (importable by features)
│   │   │   ├── app_router.dart    # GoRouter + route tree (composition root)
│   │   │   ├── workspace_shell.dart  # Adaptive bottom bar / rail for workspace tabs
│   │   │   └── not_found_page.dart
│   │   ├── theme/                 # Design tokens + ThemeData (see DESIGN_SYSTEM.md)
│   │   │   ├── theme.dart         # Barrel: import this one file
│   │   │   ├── app_colors.dart, app_typography.dart, app_spacing.dart,
│   │   │   ├── app_radii.dart, app_layout.dart, app_theme.dart
│   │   └── localization/          # See LOCALIZATION.md
│   │       ├── arb/               # app_en.arb (template), app_ar.arb
│   │       ├── generated/         # gen-l10n output (do not edit)
│   │       ├── app_locales.dart   # Supported locales, delegates, AppLanguage
│   │       ├── language_controller.dart
│   │       └── l10n.dart          # context.l10n + re-export of AppLocalizations
│   ├── core/                      # Technical infrastructure, no product features
│   │   ├── config/                # (later) environments, API base URLs
│   │   ├── constants/             # AppConstants, AppAssets
│   │   ├── errors/                # ErrorReporter
│   │   ├── network/               # (later) Dio client, interceptors
│   │   ├── storage/               # KeyValueStorage + implementations, StorageKeys
│   │   ├── services/              # (later) device/platform services
│   │   ├── realtime/              # (later) Reverb/WebSocket client
│   │   ├── notifications/         # (later) FCM integration
│   │   ├── permissions/           # (later) location/notification permissions
│   │   ├── utils/                 # (later) pure helpers
│   │   └── widgets/               # Design-system widgets (DoryButton, DoryCard, ...)
│   ├── features/                  # One folder per product feature
│   │   ├── authentication/        # splash, welcome, login, register
│   │   ├── home/                  # customer home
│   │   ├── tickets/               # customer tickets
│   │   ├── profile/               # account tab, workspace switch
│   │   ├── business_dashboard/    # business dashboard
│   │   ├── queues/                # business queues
│   │   ├── discovery/ businesses/ branches/ services/ maps/
│   │   ├── notifications/ branch_management/ employee/
│   │   └── analytics/ subscriptions/      # (later phases)
│   │       └── each feature: data/ domain/ presentation/{pages,widgets}/
│   └── shared/                    # Cross-feature product code
│       ├── components/            # language picker, coming-soon message, category visuals
│       ├── enums/                 # UserRole, AppWorkspace, BusinessCategory
│       └── models/                # (later) shared domain models
├── test/                          # Mirrors lib/
│   ├── helpers/test_app.dart      # Test containers and pump helpers
│   ├── app/ core/ shared/
├── integration_test/              # On-device tests (real bootstrap)
├── assets/fonts/ibm_plex_sans_arabic/   # Bundled font + OFL license
├── docs/                          # Project documentation
├── l10n.yaml                      # gen-l10n configuration
└── analysis_options.yaml          # Lints (stricter than the default set)
```

## Conventions

- **Files:** `snake_case.dart`, one public widget or class per file (small
  private helpers may share it).
- **Pages** end with `Page` and live in `presentation/pages/`. Feature-specific
  widgets live in `presentation/widgets/`.
- **Controllers** (Riverpod Notifiers) will live in
  `presentation/controllers/`, named `<Thing>Controller` with a
  `<thing>ControllerProvider`.
- **Design-system widgets** are prefixed `Dory` and hold no user-facing strings
  of their own: callers pass localized text.
- **Empty folders** contain a `.gitkeep` so git keeps the agreed structure.
  Delete the `.gitkeep` when the first real file lands.
- **Tests** mirror the `lib/` path: `lib/core/widgets/dory_button.dart` →
  `test/core/widgets/dory_button_test.dart`.

## Where does new code go?

| You are adding... | Put it in |
|---|---|
| A screen for one feature | `features/<feature>/presentation/pages/` |
| A widget only one feature uses | `features/<feature>/presentation/widgets/` |
| A generic visual component (no product meaning) | `core/widgets/` (prefix `Dory`) |
| A component with product meaning used by several features | `shared/components/` |
| An enum or model used by several features | `shared/enums/` or `shared/models/` |
| An API client, interceptor, or storage adapter | `core/network/`, `core/storage/` |
| A repository interface | `features/<feature>/domain/` |
| A repository implementation or DTO | `features/<feature>/data/` |
| A translatable string | both ARB files in `app/localization/arb/` |
| A new route | `AppRoutes` constant + `app_router.dart` |
