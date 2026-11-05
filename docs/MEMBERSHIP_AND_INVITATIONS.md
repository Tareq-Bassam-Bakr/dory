# Membership and Invitations

Endpoints: [API_ENDPOINTS_PHASE_03.md](API_ENDPOINTS_PHASE_03.md#team-members).

## Roles and branch access

| Role | Can |
|---|---|
| `owner` | Everything, on every branch. Exactly one per business, set at creation. |
| `manager` | With **all-branch** access: business profile, all branches and services, view the team. With **selected** access (branch manager): only the assigned branches and their services. |
| `employee` | Read-only, within their branch access. |

`business_members` gained `public_id` (ULID, for `/members/{member}`) and
`branch_access` (`all` | `selected`); assignments live in
`business_member_branch`. Owners always reach every branch. "Selected" with
no branches reaches nothing.

Only the owner manages the team (invite, change roles and branch access,
remove members, revoke invitations); managers can see it.

## Ownership protection

- The owner membership cannot be changed or removed
  (`403 membership_protected`).
- Nobody can change or remove their **own** membership through team
  management, so no member can promote themselves.
- `owner` is not an accepted role for invitations or member updates (422):
  ownership cannot be granted or transferred through the API.
- Membership identity fields (`user_id`, `business_id`, `status`,
  `public_id`) are prohibited in input.

## Invitation workflow

```
owner ── POST /businesses/{b}/invitations ──► pending ──┬─ accept  ──► membership + accepted
                                                        ├─ decline ──► declined
                                                        ├─ revoke  ──► revoked (owner)
                                                        └─ time    ──► expired
```

- **Tokens**: 64 random characters from a CSPRNG (`Str::random`). Only the
  SHA-256 hash is stored (`token_hash`, unique); the plain token exists only
  in the invitation link.
- **Expiry**: `INVITATION_EXPIRES_IN_DAYS` (default 7). Expired invitations
  answer `410 invitation_expired`.
- **Single use**: accepting, declining or revoking closes the invitation;
  further attempts get `409 invitation_not_pending`. Acceptance locks the
  row (`SELECT … FOR UPDATE`) and creates the membership in the same
  transaction, so two concurrent accepts cannot both succeed.
- **One pending invitation per email per business**, enforced by a unique
  `pending_key` column (`sha256(business_id|email)` while pending, `NULL`
  afterwards) so it also holds under concurrency. Expired invitations are
  closed before a new one is created; revoked ones free the email
  immediately.
- **Accepting requires authentication with the invited email**
  (`403 invitation_email_mismatch` otherwise). Note: email verification is
  architecturally supported but not enforced yet (no mail provider), so for
  now the token itself is the proof of inbox access. Require verified
  emails for acceptance once email delivery is configured.
- Members already in the business get `409 membership_exists` (on invite
  and on accept).
- Selected-branch invitations remember the branches; on acceptance only
  branches of the same business that still exist are assigned.
- Deleting a business revokes its pending invitations.
- Token endpoints are rate limited per IP (`invitation-token`, 30/min);
  invitation creation per user (`invitations`, 10/min).

## Delivery without a mail provider

The workflow does not depend on delivery: an invitation is valid whether or
not an email went out.

- `App\Contracts\InvitationNotifier` is the delivery abstraction;
  `MailInvitationNotifier` is the email implementation (bound in
  `AppServiceProvider`). SMS or WhatsApp can be added behind the same
  contract.
- It sends only when `INVITATION_SEND_EMAILS=true` **and** `MAIL_MAILER` is
  a real transport. The `log` and `array` mailers count as "not
  configured", because they would only write the link (and token) to a log
  file or memory.
- The API reports `delivery.status` honestly: `sent` (a real transport
  accepted it; not proof of inbox delivery), `not_configured`, or `failed`.
  The app shows exactly that and never claims an email was sent otherwise.
- For manual sharing, `share_url` is returned when `INVITATION_SHARE_LINKS`
  is on (default: on outside production). The app shows the link with a
  copy button and a warning that the link is a secret.

Links have the form `INVITATION_URL` with `{token}` replaced, default
`dory://app/invitations/{token}`. The `dory` scheme opens the app on Android
(intent filter) and iOS (`CFBundleURLTypes`); users can also paste a link or
code in "I have an invitation".

## Tokens and logs

- Only hashes are stored; audit entries hold the invitation's public id,
  never the token.
- The delivery failure log records the exception class only.
- `bootstrap/app.php` forces `zend.exception_ignore_args`, so logged stack
  traces never contain argument values (the token is an argument of the
  route binding). `InvitationAcceptanceTest` fails if a token prefix
  reaches the log.
- **Web server access logs** also see URLs. In production, keep
  `/api/v1/invitations/*` paths out of access logs (or log the route
  without the last segment), for example in nginx:

  ```nginx
  location ~ ^/api/v1/invitations/ { access_log off; try_files $uri /index.php?$query_string; }
  ```

  The PHP development server (`php artisan serve`) prints request paths to
  its console; it is for local use only.

## Audit events

`invitation.created`, `invitation.revoked`, `invitation.accepted`,
`invitation.declined`, `member.updated` (role and branch access before and
after), `member.removed`, in `business_audit_logs`.

## Flutter

| Screen | Route |
|---|---|
| Team members (with pending invitations) | `/business/dashboard/:businessId/team` |
| Invite employee | `…/team/invite` |
| Invitation details | `/invitations/:token` (public; accepting requires sign-in) |

Files under `lib/features/team/`.
