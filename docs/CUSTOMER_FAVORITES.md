# Customer favorites

Saved businesses. A deliberately small feature, not a social one.

## Signed-in customers

Favorites live on the server, in `customer_favorites`:

| Column | Notes |
|---|---|
| `user_id` | FK users, cascade |
| `business_id` | FK businesses, cascade |
| `created_at` | |

A unique index on `(user_id, business_id)` prevents duplicates, also under
concurrent requests.

- `GET /me/favorites`: paged, newest first, with live queue numbers.
- `POST /me/favorites` with `{"business": "<id>"}`: 201 when saved, 200 when
  it already was. Only public businesses can be saved.
- `DELETE /me/favorites/{business}`.

Every query goes through the caller's own id, and `user_id` in the body is
refused, so nobody can read or change another customer's favorites.

A saved business that stops being public is hidden from the list and comes
back if it returns. It can still be removed.

Discovery lists mark `is_favorite` when a valid token is sent. A token is never
required there.

## Visitors

Visitors can save too, but only on the device (`dory.local_favorites`). The
Favorites screen says so clearly and invites them to sign in. Local favorites
hold the business's id, slug, name and city.

They are **not** copied to an account when the visitor signs in: nothing here
claims they are.

## In the app

`FavoriteButton` works the same on every screen. `favoriteChangesProvider`
keeps a change visible everywhere at once. If the server refuses a change, the
heart stays as it was and the customer is told.
