# Customer experience

The customer side of DORY: finding a business, joining its queue, and
following a ticket. It runs on the Phase 04 queue engine; there is no second
engine. Nothing here needs an account.

## Navigation

The customer workspace has five tabs (`lib/app/router/app_router.dart`):

| Tab | Route | Screen |
|---|---|---|
| Home | `/customer/home` | Greeting, location, search, categories, live sections |
| Explore | `/customer/explore` | Search, filters, results |
| My Tickets | `/customer/tickets` | Ticket center |
| Favorites | `/customer/favorites` | Saved businesses |
| Profile | `/customer/account` | Account, language, activity, workspace switch |

Detail screens open above the tabs with `context.push`, so back returns to
whichever tab they came from:

| Route | Screen |
|---|---|
| `/customer/map` | Map |
| `/customer/business/:slug` | Business profile and branches |
| `/customer/branch/:branchId` | Branch and its services |
| `/customer/queue/:queueId` | Queue details and joining |
| `/customer/ticket/:ticketId` (`?guest=1`) | Digital ticket |
| `/customer/profile/edit` | Name and phone |

## Visitors, customers and business owners

- **Visitors** can browse, search, use the map, join queues that allow
  guests, and follow their tickets. Account creation is suggested, never
  required.
- **Customers** also keep their tickets and favorites on the server.
- **Business owners and staff** are customers too. After signing in they
  land in the business workspace as before; a storefront button on the
  customer home and the workspace tile in Profile move between the two.
  Business and employee navigation did not change.

## Home

Each section loads and fails on its own, so one problem never blanks the
screen.

| Section | Source | Empty or failed state |
|---|---|---|
| Header | Session, location | Notifications button is a placeholder for a later phase, and says so |
| Search | Opens Explore | |
| Categories | `GET /business-categories` | Retry |
| Queues accepting tickets | `GET /discovery/queues` | "No queues open right now" |
| Nearby businesses | `GET /discovery/nearby` | No location: invitation to set one. Nothing found: suggests a larger radius |
| Businesses on DORY | `GET /discovery/businesses` | "No businesses yet" |

The last section is what the plan calls "featured". It is an ordering, not a
paid placement: businesses a customer can use right now come first, then the
best rated. Nothing is promoted.

Only real records are shown. There is no production seed of businesses; see
[Trying it locally](#trying-it-locally).

## From business to queue

1. Business page: logo or category icon, cover, description, category, real
   rating, published contact details, branches.
2. Branch page: address, city, distance, and the services offered there.
3. Service: its queue state, approximate wait, and whether it accepts guests.
4. Queue page: everything needed to decide, then the join button.

There are **no opening hours in the backend, so none are shown or implied**.
No screen says a branch is open. Availability comes only from the current
queue session, read from the server each time: a past session never makes a
queue look open.

The queue page explains why joining is not possible: closed, paused, full,
service unavailable, branch inactive, business unavailable, or offline. The
server checks again when the customer joins, whatever the screen showed.

## Design

The screens use the DORY design system (`lib/core/widgets`): primary
`#1557A6`, secondary `#3B82F6`, white rounded cards, the shared spacing scale.
All text comes from the ARB files, with a test that keeps English and Arabic
in step. Every screen is rendered in both languages on a small phone and a
tablet by `test/app/layout_smoke_test.dart`; an overflow fails the test.
There is no dark theme yet, because the theme architecture does not have one.

## Trying it locally

A new database has no businesses, so the customer screens show their empty
states. `CustomerDemoSeeder` adds a demo business with an open queue. It runs
only outside production, and only with a demo password set:

```powershell
$env:SEED_DEMO_PASSWORD='choose-one-locally'; D:\php84\php.exe artisan db:seed
```

Running it again reopens the queue on a later day. Demo records are named as
demos and never appear in production: `DemoDataTest` checks that.

## Testing on a device

`integration_test/phase05_e2e_test.dart` walks the guest journey on a real
device: browse, join, restart the app, leave the queue. It needs a running API
and a public business with an open queue that allows guests, such as the demo
one. It joins and cancels a real ticket, so never point it at production:

```powershell
D:\php84\php.exe artisan serve
adb reverse tcp:8000 tcp:8000
flutter test integration_test/phase05_e2e_test.dart -d DEVICE_ID
```

Keep the phone unlocked: it stalls if the screen locks during the build.

Related: [BUSINESS_DISCOVERY.md](BUSINESS_DISCOVERY.md),
[CUSTOMER_TICKET_EXPERIENCE.md](CUSTOMER_TICKET_EXPERIENCE.md),
[MAPS_AND_LOCATION.md](MAPS_AND_LOCATION.md).
