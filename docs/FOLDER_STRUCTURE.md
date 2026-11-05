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
│   │   ├── config/                # ApiConfig (base URL, timeouts)
│   │   ├── constants/             # AppConstants, AppAssets
│   │   ├── errors/                # ErrorReporter, AppFailure
│   │   ├── network/               # ApiClient, envelope, ApiException, token interceptor, providers
│   │   ├── storage/               # KeyValueStorage (preferences), SecureStore (tokens), StorageKeys
│   │   ├── services/              # (later) device/platform services
│   │   ├── realtime/              # (later) Reverb/WebSocket client
│   │   ├── notifications/         # (later) FCM integration
│   │   ├── permissions/           # (later) location/notification permissions
│   │   ├── utils/                 # (later) pure helpers
│   │   └── widgets/               # Design-system widgets (DoryButton, DoryCard, ...)
│   ├── features/                  # One folder per product feature
│   │   ├── authentication/        # session: data (datasources, models, repository),
│   │   │                          #   domain (entities, repository, usecases),
│   │   │                          #   presentation (providers, pages, validation)
│   │   ├── home/                  # customer home (categories from the API)
│   │   ├── tickets/               # customer tickets
│   │   ├── profile/               # account tab (user, logout), workspace switch
│   │   ├── business_dashboard/    # "My businesses" (business workspace home)
│   │   ├── businesses/            # categories, create, onboarding, overview, edit
│   │   ├── branch_management/     # branch list, details, create/edit form
│   │   ├── services/              # services list, create/edit form
│   │   ├── team/                  # members, invitations, invitation link
│   │   ├── queues/                # business queues (later phase)
│   │   ├── discovery/ branches/ maps/ notifications/ employee/
│   │   └── analytics/ subscriptions/      # (later phases)
│   │       └── each feature: data/ domain/ presentation/{pages,providers,widgets}/
│   └── shared/                    # Cross-feature product code
│       ├── components/            # language picker, async view, failure messages,
│       │                          #   status chip, confirm dialog, category icons
│       ├── enums/                 # UserRole, AppWorkspace, BusinessRole
│       └── models/                # (later) shared domain models
├── test/                          # Mirrors lib/
│   ├── helpers/                   # test_app (containers, pumpAppAt), fake HTTP transport,
│   │                              #   fake auth repository, API JSON fixtures
│   ├── app/ core/ shared/ features/
├── integration_test/              # On-device tests (real bootstrap; phase03_e2e needs the API)
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
- **Providers and controllers** (Riverpod) live in
  `presentation/providers/`: `<Thing>Controller` + `<thing>ControllerProvider`
  for state, `<Thing>Actions` for mutations that refresh affected data.
- **No API calls in widgets**: widgets use providers and actions, which use
  repository contracts from `domain/`.
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
