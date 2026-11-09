# Device token security

An FCM registration token lets a server send messages to one app install.
DORY treats it as a secret. Tests: `tests/Feature/Notifications/PushDevicesTest.php`.

## Storage (`push_devices`)

| Column | Content |
|---|---|
| `public_id` | ULID: the only id the API returns |
| `token_encrypted` | the token, encrypted with `APP_KEY` (Laravel `encrypted` cast) |
| `token_hash` | SHA-256 of the token, for lookups without decrypting |
| `user_id` / `queue_ticket_id` | the owner: a user, or one guest ticket |
| `owner_key` | `user:{id}` or `ticket:{id}`; unique with `token_hash` |
| `platform`, `locale`, `app_version`, `last_seen_at` | no personal data |
| `revoked_at` | a revoked row is never used again |

- The token is never returned by the API, not even to its owner
  (`PushDeviceResource` has no token field), and never written to logs.
  Errors log the device's public id.
- The app does not store the token either; Firebase keeps it. The app
  remembers only the device ids the server returned (not secret), in one
  listed preference, `dory.push_devices`: a small JSON map per account and
  per guest ticket. The phone's preference storage accepts only listed keys,
  and the first version used one key per id, which a phone refuses.

## Endpoints

| Endpoint | Proof |
|---|---|
| `POST /api/v1/devices/register` | Sanctum token, active account |
| `DELETE /api/v1/devices/{device}/unregister` | Sanctum token; only your own devices (others: 404) |
| `POST /api/v1/public/tickets/{ticket}/devices/register` | `X-Ticket-Token` header of that ticket |
| `DELETE /api/v1/public/tickets/{ticket}/devices/unregister` | `X-Ticket-Token`; device id in the body |

Body: `token` (20 to 4096 characters, FCM's character set), `platform`
(`android`/`ios`), `locale` (`en`/`ar`), optional `app_version`, optional
`replaces` (the device id of the previous token). Rate limit: 20 per minute
per user, or per IP for guests.

The guest ticket token travels only in the `X-Ticket-Token` header: never in
a URL, a channel name, a push payload or a log. A guest can register only
for an active ticket. The same rules as in Phase 04 apply: a wrong token,
someone else's ticket, and a ticket that does not exist all give the same
404.

## Rules

- **One account per phone.** When a phone registers for account B, the
  rows of the same token for other accounts are revoked, so the previous
  account stops getting alerts on that phone.
- **Sign-out** removes the registration first.
- **Idempotent.** Registering the same token for the same owner updates
  that row; it never creates a second one.
- **Rotation.** `replaces` revokes the old row only if it belongs to the same
  owner, so nobody can revoke a stranger's device.
- **Invalid tokens.** When FCM answers "not registered", every row with that
  token is revoked.
- A guest's registration covers one ticket. A second guest ticket on the
  same phone gets its own row.
- Revoked rows are kept, as the history of a device. Removing old rows is
  not part of Phase 06.
