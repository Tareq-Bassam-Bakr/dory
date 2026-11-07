# Map tile provider

**OpenStreetMap data and OpenStreetMap's public tile servers are different
things.** The data is open. `tile.openstreetmap.org` is a service run by
volunteers on donated resources, with no guarantee of availability, and its
[usage policy](https://operations.osmfoundation.org/policies/tiles/) does not
allow heavy use. It is not a free production tile host. DORY must not send
production traffic there.

## How DORY is set up

| Build | Tiles |
|---|---|
| Debug and profile | `tile.openstreetmap.org`, for light development use only |
| Release without configuration | **No map.** The screen says so and offers the list |
| Release with `DORY_MAP_TILE_URL` | The configured provider |

A release build can therefore never send traffic to the public server by
accident. The provider is chosen in `lib/core/config/map_config.dart` and read
through `mapTileConfigProvider`. Discovery logic and the map page do not know
which provider is used.

## Configuring a production provider

Choose a provider whose terms allow your traffic and commercial use, or host
tiles yourself. Then build with:

```powershell
flutter build apk --release `
  --dart-define=DORY_API_BASE_URL=https://api.example.com/api/v1 `
  --dart-define=DORY_MAP_TILE_URL=https://tiles.example.com/{z}/{x}/{y}.png?key={key} `
  --dart-define=DORY_MAP_TILE_KEY=YOUR_KEY `
  --dart-define=DORY_MAP_ATTRIBUTION="Example Maps, OpenStreetMap contributors" `
  --dart-define=DORY_MAP_ATTRIBUTION_URL=https://www.openstreetmap.org/copyright
```

| Variable | Meaning |
|---|---|
| `DORY_MAP_TILE_URL` | `{z}/{x}/{y}` template. `{key}` is replaced by the key |
| `DORY_MAP_TILE_KEY` | Optional. Never commit it |
| `DORY_MAP_ATTRIBUTION` | The credit the provider requires |
| `DORY_MAP_ATTRIBUTION_URL` | Where tapping the credit leads |

A key built into a mobile app can be extracted from it. Use one restricted to
this app where the provider allows that, or serve tiles through your own proxy.

No key, paid or otherwise, is written in this repository.

## Rules that apply to every provider

- **Attribution.** The credit is always visible and tappable, through
  flutter_map's `SimpleAttributionWidget`, and nothing on the map covers it (a
  test checks this). Credit both the tile provider and OpenStreetMap
  contributors: the data is under the
  [ODbL](https://www.openstreetmap.org/copyright).
- **Identification.** Tile requests carry a User-Agent built from
  `userAgentPackageName` (`com.dory.app`).
- **No bulk downloads.** Tiles are fetched only as the customer views the map.
  The app has no pre-fetch or offline download.
- **Caching.** flutter_map's network tile provider caches tiles as servers
  expect.
- **No scraping** of any tile server.

## Geocoding

There is none, so there are no geocoding costs. Cities for the manual picker
come from the branches themselves (`GET /discovery/cities`), and branch
coordinates are entered by each business.
