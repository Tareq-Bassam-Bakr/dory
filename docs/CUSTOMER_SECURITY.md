# Customer security

What protects customers and guests, and which test proves it. Flutter's
visibility rules are never a security control: every rule below is enforced by
the server or by the operating system.

## Public endpoints

| Risk | Control | Test |
|---|---|---|
| Unpublished data | `publiclyVisible` scopes; anything else is a 404 | `BusinessDiscoveryTest`, `CatalogueDetailsTest` |
| Fields leaking by accident | Public resources are built field by field | `test_a_card_exposes_only_public_fields` |
| Members, owners, staff | Never loaded into a public resource | Same |
| Other customers in a queue | Public queue data is counts and rules | `test_public_queue_data_never_contains_people` |
| Queue id revealing a hidden business | 404 unless the business is public | `test_a_queue_id_reveals_nothing_...` |
| SQL injection | Bound terms; `%` and `_` escaped; ids pattern-matched; coordinates validated and formatted as floats | `test_sql_in_a_search_is_only_text`, `test_malformed_ids_...` |
| Scraping and overload | Rate limits, page limits, radius limits | `test_discovery_is_rate_limited_per_client`, `test_invalid_input_is_rejected` |
| Unauthenticated routes added by accident | An explicit allowlist | `RouteProtectionTest` |

## Status checks on the backend

`TicketJoinService` refuses a join while the business, branch or service is not
available, even with a session still open. Phase 04 only checked the queue
itself; this was found during Phase 05. It applies to signed-in joins, guest
joins and the web page.

## Ownership

| Rule | Control |
|---|---|
| A customer reaches only their own tickets | `{myTicket}` resolves through the caller's id; anything else is a 404 |
| Tickets are not addressed by sequential ids | Public ids, random for tickets |
| Favorites are the caller's own | Queries use the caller's id; `user_id` in input is refused |
| Ratings | Only the holder of a completed ticket; never the business's team; once |

Tests: `CustomerQueueAccessTest`, `FavoritesAndReviewsTest`.

## Guest tokens

| Rule | Control | Test |
|---|---|---|
| Never stored in plaintext on the server | SHA-256 hash | `test_a_guest_joins_by_queue_id_...` |
| Never in a URL | `X-Ticket-Token` header; a token in the query string is ignored | `test_a_guest_ticket_is_not_readable_...` |
| Never in SharedPreferences | `SecureStore` only | `guest_ticket_vault_test.dart` |
| Never in logs | No HTTP logging interceptor, no `print` in the app, no token in `toString` | `customer_token_hygiene_test.dart` |
| Never shown | Not part of `CustomerTicket` | `customer_ticket_flow_test.dart` |
| Not sent elsewhere | Set only by the ticket repository, for the configured API | `customer_token_hygiene_test.dart` |

The app has no analytics or crash-reporting service. `ErrorReporter` writes to
the local developer log, and API errors never contain request or response
bodies.

## Location

See [MAPS_AND_LOCATION.md](MAPS_AND_LOCATION.md). Location is asked for only on
request, read once, kept in memory, and rounded to about 100 metres. The backend
never stores or logs it. There is no background permission, and
`location_policy_test.dart` fails if one is added.

## Findings during this phase

| Finding | Fix |
|---|---|
| A queue kept accepting joins after its business, branch or service was deactivated | Join guard in `TicketJoinService` |
| On MariaDB, `joined_at`, `opened_at`, `assigned_at` and an invitation's `expires_at` changed on any update of their row (found by looking at a real ticket) | Migration `stop_timestamps_updating_themselves`; `TimestampColumnsTest` |
| `GET /public/queues/{id}` would have shown a suspended business's name and address | 404 unless the business is public |
| A second tap in the same frame sent a second join request | Synchronous guard. The idempotency key already prevented a duplicate ticket |
| Device coordinates in query strings could reach access logs at full precision | Rounded in the app. Production logs should also drop these query strings |
| Riverpod retried failed requests in the background, including 404s | Automatic retry turned off for API providers |
| Older strings promised notifications and "live" positions | Rewritten |
| `INTERNET` was declared only in the debug manifest | Added to the main manifest |

## Open points

- Guests are limited per IP and by capacity. One person could still take
  several guest tickets from different addresses, as in Phase 04.
- Coordinates appear in query strings, because the API follows the plan's
  parameters. Configure access logs accordingly in production.
- A map key built into the app can be extracted; see
  [OSM_TILE_PROVIDER.md](OSM_TILE_PROVIDER.md).
