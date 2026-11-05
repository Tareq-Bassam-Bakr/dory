# Design System

The DORY design system lives in `lib/app/theme/` (tokens and `ThemeData`) and
`lib/core/widgets/` (components). Import tokens with
`package:dory_app/app/theme/theme.dart` and components with
`package:dory_app/core/widgets/widgets.dart`.

**Principles:** modern global SaaS, clean white surfaces on a light blue
background, rounded cards, generous spacing, and minimal chrome. Every screen
must work in Arabic (RTL) and English (LTR).

## Colors — `AppColors`

| Token | Hex | Use |
|---|---|---|
| `primary` | `#1557A6` | Brand, primary buttons, active navigation, links |
| `secondary` | `#3B82F6` | Accents, gradient end, illustrations |
| `background` | `#EFF6FF` | Scaffold / page background |
| `surface` | `#FFFFFF` | Cards, inputs, sheets, navigation bar |
| `textPrimary` | `#0F172A` | Headings and body text |
| `textSecondary` | `#475569` | Supporting text |
| `textMuted` | `#64748B` | Hints, placeholder icons |
| `primaryContainer` | `#DBEAFE` | Selected states, icon backgrounds, info notices |
| `border` / `borderStrong` | `#E2E8F0` / `#CBD5E1` | Card borders, dividers / outlined buttons |
| `success`, `warning`, `error` | `#15803D`, `#B45309`, `#DC2626` | Semantic states (each has a `*Container` tint) |

**Accessibility:** `primary` on white has a contrast ratio of about 7:1 (AAA).
`secondary` on white is only about 3.7:1, so **never use `secondary` for small
text or as a background behind white body text.** Keep it for icons, large
elements, and gradients.

In widgets, prefer `Theme.of(context).colorScheme` over `AppColors` so a future
dark theme works without changes. Use `AppColors` directly only for values the
`ColorScheme` does not model (for example `success`).

## Typography — `AppTypography`

- Family: **IBM Plex Sans Arabic** (weights 400, 500, 600, 700), bundled under
  the SIL Open Font License 1.1. It covers Arabic and Latin, so one family
  serves both languages.
- **Letter spacing is always 0.** Positive tracking breaks the joining of
  Arabic letters. The only exception is the Latin "DORY" wordmark.
- Line heights are 1.2–1.5, slightly taller than Material defaults because
  Arabic glyphs have deeper ascenders and descenders.

| Style | Size / weight | Typical use |
|---|---|---|
| `headlineMedium` | 24 / 700 | Screen headlines (welcome, auth) |
| `headlineSmall` | 20 / 600 | Home headline |
| `titleLarge` | 18 / 600 | App bar titles |
| `titleMedium` | 16 / 600 | Section and card titles |
| `bodyLarge` | 16 / 400 | Primary body text |
| `bodyMedium` | 14 / 400 | Secondary body text (secondary color) |
| `bodySmall` | 12 / 400 | Captions, metric labels |
| `labelLarge` | 14 / 600 | Buttons |

Always use `Theme.of(context).textTheme.*`. Never create ad-hoc `TextStyle`s
in features.

## Spacing, radii, layout

- `AppSpacing` uses a 4dp grid: `xxs 2`, `xs 4`, `sm 8`, `md 12`, `lg 16`,
  `xl 24`, `xxl 32`, `xxxl 48`. The page gutter is `lg`.
- `AppRadii`: `sm 8`, `md 12` (buttons, inputs), `lg 16` (cards), `xl 24`,
  `full` (pills, chips).
- `AppLayout`: compact windows are < 600dp. Content is capped at 640dp, and
  forms at 480dp, so screens stay readable on tablets.
- Use `EdgeInsetsDirectional`, `AlignmentDirectional`, and `start`/`end`
  instead of `left`/`right`, so layouts mirror correctly in RTL.

## Components — `core/widgets/`

| Widget | Purpose | Notes |
|---|---|---|
| `DoryButton` | All buttons | Variants `primary` (filled), `secondary` (outlined), `tertiary` (text). `isLoading` shows a spinner, disables taps, and keeps the size and the screen-reader label. `expand` fills the width (default). `onPressed: null` disables it. |
| `DoryTextField` | Text inputs | Label, hint, prefix icon, validator, autofill. `passwordVisibilityLabels` makes it a password field with a show/hide toggle (and disables suggestions and autocorrect). `textDirection: ltr` for emails and phone numbers in Arabic layouts. |
| `DoryCard` | Grouped content on white | 16dp radius, 1px border, no elevation. `onTap` adds an ink ripple. |
| `DoryLoadingIndicator` | Loading state | Centered spinner + optional message. |
| `DoryEmptyState` | Nothing to show yet | Icon, title, message, optional action. |
| `DoryErrorState` | Failure | Error colors and an optional retry action. |
| `DoryStatusView` | Shared layout behind empty/error states | Use the specific wrappers in features. |
| `DoryNotice` | Inline info/warning banner | Announced as a live region. |
| `DoryMetricTile` | Labelled figure | A `null` value shows "—" and announces "no data", never a fake 0. |
| `DorySectionHeader` | Section title (+ trailing) | Marked as a semantic header. |
| `DoryPageScaffold` | Standard page | App bar, safe area, gutters, max content width, optional scrolling. |
| `DoryLogo`, `DoryLogoMark`, `DoryWordmark` | Brand | Placeholder rendering built from tokens. **Replace with the official vector logo** when brand assets are delivered. |

Design-system widgets **never contain hard-coded user-facing text**. Callers
pass localized strings.

## Theme — `AppTheme.light`

`AppTheme.light` builds the `ThemeData` once: an explicit Material 3
`ColorScheme` (no seed-derived drift and no elevation tint) plus component
themes for app bars, cards, buttons, inputs, chips, navigation bar and rail,
list tiles, snackbars, dividers, and progress indicators. Visual values belong
in the theme, not in widgets.
