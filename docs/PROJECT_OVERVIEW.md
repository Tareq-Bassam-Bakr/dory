# Project Overview

## Vision

DORY is a global smart queue and appointment management platform. It replaces
physical waiting lines with virtual queues that customers can follow from
anywhere.

**Customers** can:

- Discover nearby businesses.
- Browse services and branches.
- Join virtual queues and receive digital tickets.
- Track their queue position and estimated waiting time.
- Receive real-time notifications as their turn approaches.

**Businesses** can:

- Register and manage businesses, branches, and services.
- Manage queues and employees.
- Call the next customer.
- Monitor waiting times and analytics.

DORY serves many business categories from day one: restaurants, clinics,
salons, banks, government service centers, repair shops, and other service
providers. **One generic queue engine serves every category.** Categories only
describe and group businesses; no queue logic may branch on category.

## Users and roles

| Role | Workspace | Notes |
|---|---|---|
| Customer | Customer | Joins queues, holds tickets |
| Business Owner | Business | Owns one or more businesses |
| Branch Manager | Business | Manages specific branches |
| Employee | Business | Serves customers at counters |
| Super Admin | Administration | Platform operations (no mobile shell planned yet) |

A user holds a **set** of roles. For example, a business owner can also book as
a customer. The app is organized into **workspaces** (customer and business),
each with its own navigation shell, and users switch between the workspaces
their roles grant. See [ARCHITECTURE.md](ARCHITECTURE.md#roles-and-workspaces).

## Platform

| Component | Technology | Phase |
|---|---|---|
| Mobile app | Flutter, Riverpod, GoRouter | Phase 01+ |
| Backend API | Laravel 13, MySQL, Redis, Sanctum (`backend/`) | Phase 02 (foundation, auth, multi-tenancy) |
| Real-time updates | Laravel Reverb | Later phase |
| Push notifications | Firebase Cloud Messaging | Later phase |
| Maps | OpenStreetMap (flutter_map) | Later phase |
| AI services | Python FastAPI, only where justified | Later phase |

## Phase 01 scope: Architecture & Foundation

Delivered:

- Flutter project (Android and iOS) with a clean, feature-first architecture.
- Riverpod dependency injection and GoRouter navigation.
- The DORY design system: tokens, theme, and reusable components.
- Arabic (RTL) and English (LTR) localization with a persisted language choice.
- Screens: splash, welcome, login and registration layouts, customer home
  shell, business dashboard shell, tickets, queues, and account.
- The role and workspace model, with customer/business navigation shells.
- Unit, widget, layout, and on-device integration tests.
- Documentation (this folder).

Explicitly **not** in Phase 01:

- Backend integration or networking.
- Authentication. The login and registration screens are layouts with
  disabled submit buttons and an explanatory notice.
- Queue, ticket, business, or analytics functionality. Screens show honest
  empty states, never invented data.
- Maps, notifications, real-time updates, and AI.

### Phase 01 product decisions to review

- **Guest browsing.** The welcome screen offers "Explore as a guest", which
  opens the customer workspace without an account. Discovery without sign-in
  is common for this kind of product, but joining a queue will require an
  account.
- **Workspace switch is open to everyone in Phase 01.** No session exists yet,
  so the account screen lets anyone open the business workspace (which shows
  only empty states). From Phase 02, the switch and the business routes are
  gated by role.
- **Login and registration fields are provisional** (email + password, plus
  name and phone for registration). The final set depends on the
  authentication method chosen in Phase 02, for example phone and OTP.
