# Maps and location

## Location rules

| Rule | How it is met |
|---|---|
| Never required | Every screen works without it. Nearby sections explain how to set a location |
| Requested only when needed | `CustomerLocationController.useDevice()` runs only from "Use my location" |
| Explained first | The location sheet says why before any system prompt appears |
| While in use only | No background permission on Android or iOS. `test/core/location/location_policy_test.dart` fails if one is added |
| Approximate supported | The app reads low accuracy, which works with Android's approximate option |
| One reading, no tracking | A single `getCurrentPosition`. No position stream exists anywhere in the code |
| No location history | The device position is kept in memory only. It is gone when the app closes |
| Manual fallback | Cities come from `GET /discovery/cities`, with no geocoding service |
| Denied | The customer is told, and the city list stays available |
| Permanently denied | A button opens system settings |
| Location off | A button opens location settings |

Positions are rounded to three decimals, about 100 metres, before they are
used. Coordinates travel in query strings, so they can reach web server access
logs; rounding keeps what could be logged imprecise. Production access logs
should drop query strings for `/api/v1/discovery/*` too.

A city chosen by hand is remembered on the device (`dory.chosen_city`). It is
not personal location data, and it is removed when a device position replaces
it or the customer clears it. The search radius is remembered too.

Tests never use GPS: `LocationService` is an interface, and
`FakeLocationService` replaces it.

## Distance

The server filters and sorts, in `GeoCircle`:

1. A bounding box uses the index on `(status, latitude, longitude)`.
2. The circle and the order use the equirectangular approximation: plain
   arithmetic that runs identically on MySQL, MariaDB and SQLite (SQLite has
   no trigonometric functions by default), and is accurate to well under 1% at
   the allowed radiuses.
3. Distances shown to customers use the haversine formula, in PHP.

It handles the 180th meridian and the poles. Branches without coordinates are
never placed on a map or in nearby results.

## The map

`DiscoveryMapPage` uses [flutter_map](https://docs.fleaflet.dev):

- Branch markers (filled green when accepting tickets), and the customer's
  marker only for a device location.
- Tapping a marker opens a preview card, which leads to the branch.
- Recenter, category filter, and a button back to the list.
- Up to 100 branches around the location, nearest first.

The page knows nothing about the tile provider: it reads
`mapTileConfigProvider`. See [OSM_TILE_PROVIDER.md](OSM_TILE_PROVIDER.md).

### flutter_map and material_ui

This app uses `material_ui`, not `package:flutter/material.dart`, and the two
have different `Theme` and `Material` classes. flutter_map imports the latter,
but `FlutterMap`, `TileLayer` and `MarkerLayer` use no Material-only widget, so
they work. Two points to keep in mind:

- `SimpleAttributionWidget` needs an explicit `backgroundColor`, or it reads
  the wrong `Theme`.
- `RichAttributionWidget` uses an `IconButton` that needs a Material ancestor
  this app does not have, and it hides the credit behind a button. Do not use
  it.
