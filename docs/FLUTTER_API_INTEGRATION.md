# Flutter API Integration

How the app talks to the Laravel API, keeps the session and handles errors.

## Configuration

The base URL comes from a compile-time define:

```bash
flutter run --dart-define=DORY_API_BASE_URL=https://api.example.com/api/v1
```

- Debug and profile builds default to `http://127.0.0.1:8000/api/v1`.
- Release builds have **no default** and refuse to start without the
  define: they never talk to a development address by accident.
- Plain HTTP is allowed only in debug builds
  (`android/app/src/debug/AndroidManifest.xml`); release builds accept
  HTTPS only.

### Running against the local API on a USB phone

```powershell
cd backend
```

```powershell
D:\php84\php.exe artisan serve
```

In a second terminal, forward the phone's port 8000 to the computer:

```powershell
adb reverse tcp:8000 tcp:8000
```

```bash
flutter run -d <device-id>
```

`adb reverse` has to be repeated after reconnecting the phone. Without USB,
run `php artisan serve --host=0.0.0.0` and pass the computer's LAN address:
`--dart-define=DORY_API_BASE_URL=http://192.168.x.y:8000/api/v1`.

## Layers

```
Widget ─► Riverpod provider / actions ─► Repository (domain contract)
                                             │
                                             ▼
                        API repository / data source ─► ApiClient ─► Dio ─► Laravel
```

- Widgets never call HTTP. They read Riverpod providers and call
  repository-backed actions (`BusinessActions`, `BranchActions`,
  `ServiceActions`, `TeamActions`) or the `AuthController`.
- `core/network/`: `ApiClient` (envelope parsing; returns `ApiEnvelope` or
  throws `ApiException` with the server `code` and field errors),
  `AuthTokenInterceptor`, `network_providers.dart` (Dio, base URL, token
  holder, session events). No logging interceptor is installed, because
  requests carry passwords and tokens.
- Repositories convert `ApiException` into domain failures: `AuthFailure`
  for authentication and `AppFailure` for everything else. Presentation
  maps them to localized messages (`auth_failure_messages.dart`,
  `shared/components/failure_message.dart`).

Authentication follows Clean Architecture under
`lib/features/authentication/`: `domain/entities`,
`domain/repositories/auth_repository.dart`, `domain/usecases/`,
`data/models`, `data/datasources` (remote: Dio; local: secure storage),
`data/repositories/auth_repository_impl.dart`, `presentation/providers`
(`AuthController`), `presentation/pages`. The spec's `presentation/screens/`
is the existing `presentation/pages/` folder (Phase 01 convention).

## Session

| Moment | What happens |
|---|---|
| Login / registration | `POST /auth/login` or `/auth/register` with `device_name`; the token, its expiry and the user are saved in secure storage |
| App start | Splash waits for the brand minimum and the session check. A stored token is verified with `GET /auth/me` |
| Token expired (by `expires_at`) | Discarded without a request |
| Server rejects the token (401/403 on `/auth/me`) | Session removed; welcome |
| Server unreachable at start | The last known user is restored as *stale*; the server re-checks the token on the next request |
| Any 401 on an authenticated request | `SessionEvents` → `AuthController` ends the session; the login screen says the session ended |
| Logout | `POST /auth/logout` revokes the token (best effort); the device always forgets it |

**Secure storage**: `flutter_secure_storage` 11 (`core/storage/secure_store.dart`).
On Android the values are AES-GCM encrypted with a key held in the Android
Keystore; on iOS they are in the Keychain with
`first_unlock_this_device` (never synced to other devices). Tokens are
never written to `shared_preferences`.

The tenant data providers watch the signed-in user's id, so after a logout
or with another account no previous user's data is shown.

## Navigation guards

`app/router/auth_redirect.dart` (applied to every navigation; re-evaluated
when the session changes):

- The business workspace (`/business/...`) needs a session. While the
  session is being restored the user waits on the splash; signed out, they
  go to login. Both remember the destination in `?from=`.
- Welcome, login and registration are for signed-out users; signed-in users
  continue to `from` or to their home (business workspace if they work in a
  business, otherwise customer home).
- `?from=` only accepts in-app paths (no schemes, hosts or `//`), so it
  cannot be used as an open redirect.

These guards shape the UI only. The API authorizes every request.

## Errors and states

- Every data screen uses `AsyncValueView`: loading indicator, error state
  with a working retry, data.
- Forms show server validation messages under the matching field and a
  summary notice; buttons show progress and ignore repeated taps.
- `permissions` returned by the API decide which actions are shown.

## Tests

- `test/helpers/fake_http_adapter.dart` replaces only Dio's transport, so
  tests run the real repositories, `ApiClient`, interceptors and parsing.
- `test/features/authentication/data/auth_repository_impl_test.dart`:
  login, failures, registration validation, token restoration (valid,
  expired, revoked, offline), logout.
- `test/features/authentication/presentation/`: controller (401 ends the
  session), validators, login and registration screens.
- `integration_test/phase03_e2e_test.dart`: on a device against the real
  API (register, onboarding, restart).
