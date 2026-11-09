# Phase 07 report: AI intelligence

Date: 2026-11-09 · Baseline: tag `phase-06-baseline-for-07` → `b540e4b` ·
Final commit: see [Commits](#commits).

## Summary

DORY now predicts waits from what is actually happening at the counter,
forecasts busy and quiet hours from real arrivals, and gives owners and
managers recommendations with the numbers behind them, in English and Arabic.

These capabilities are **rule-based and statistical**, computed from each
business's own records. No external AI service, API key, paid provider or
new dependency is involved, and no language model is in use yet. The
architecture has a defined place for one
([AI_ARCHITECTURE.md](AI_ARCHITECTURE.md#adding-a-language-model-later)). With
too little data, the API and the app say so instead of guessing.

No new tables or migrations: `dory_db` needed no change.

## Delivered

| Scope | Result |
|---|---|
| Smart wait-time prediction | `SmartWaitTimeEstimator`, the new default. It adds to the Phase 04 history: customers at the counter (their remaining time), staff actually serving, a likely range and a confidence. The ticket shows "Usually 12 to 19 min" |
| Peak-time forecasting | `GET /businesses/{id}/peak-times`: average customers per weekday and hour in the branch's local time, busy / normal / quiet / closed levels, busiest and quietest hours, the next 7 days |
| Business insights | `GET /businesses/{id}/insights`: 30-day metrics against the previous 30 days, and 8 kinds of recommendation with explicit thresholds and their numbers |
| AI architecture | `WaitTimeEstimator` and the new `InsightNarrator` contracts; analysis separated from wording; `DORY_AI_NARRATOR` selects the narrator; a documented path for a language model that receives aggregated facts only |
| Insufficient data | `status: insufficient_data` with the counts and minimums. Peak times need 50 tickets over 14 days. Recommendations need 20 tickets in the period; the metrics still show |
| Tenant isolation | Owners and managers only; employees 403, non-members 404; managers of selected branches see only those; a foreign branch is `branch_not_found` |
| Arabic and English | Server texts in `lang/{en,ar}/insights.php`, 28 app strings in both languages, RTL checked on a phone |
| Flutter | `BusinessInsightsPage` ("Insights and peak times" on the business overview, shown by `permissions.view_insights`), built from the design-system components. Branding and app icon unchanged |

Details: [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md),
[WAIT_TIME_ESTIMATION.md](WAIT_TIME_ESTIMATION.md).

## Main changed files

| Area | Files |
|---|---|
| Backend, new | `app/Services/Intelligence/*`, `app/Contracts/InsightNarrator.php`, `app/Http/Controllers/Api/V1/Intelligence/*`, `app/Services/Queues/ServiceProfile.php`, `lang/{en,ar}/insights.php` |
| Backend, changed | `HistoricalWaitTimeEstimator` (exposes its profile; same estimates), `WaitEstimate` (range, confidence), `AppServiceProvider`, `BusinessPolicy`, `BusinessAbilities`, `routes/api.php`, `config/dory.php` |
| Flutter, new | `lib/features/analytics/*` |
| Flutter, changed | Routes, business permissions, business overview, ticket screen and entity, ARB files |

## Test results

All on 2026-11-09, on this machine, on the final code.

| Suite | Result |
|---|---|
| Backend, MySQL 8.4.13 (`composer test:mysql`, local) | ✅ **663 passed** (5708 assertions) |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **663 passed** (5708 assertions) |
| Backend, SQLite (`composer test`) | ✅ **654 passed, 9 skipped** (5516 assertions). The 9 need MySQL or MariaDB |
| Pint | ✅ passed |
| Flutter (`flutter test`) | ✅ **584 passed** |
| Flutter analyzer | ✅ no issues |
| `dart format` | ✅ no changes |
| On-device tests (`integration_test`) | Not rerun in this phase. The screens were checked by hand on the phone (below) |
| GitHub Actions | ⚠️ not run: it still does not start for this repository (account-level) |

Phase 07 added 23 backend tests (640 → 663) and 6 Flutter tests (578 → 584).
No test was removed or weakened. Three existing tests changed, each because
the behaviour changed on purpose:

- `WaitTimeEstimationTest`: the default binding is now the smart estimator.
  Its tests of the historical estimator are unchanged and still pass.
- `GuestJoinTest`: with one customer being served and one waiting, a newcomer's
  estimate is 24 minutes, not 12. The old estimate ignored the customer at
  the counter.
- `BusinessCreationTest`: the permissions list gained `view_insights`.

## Checked in the real app

On the TECNO KM7 (Android 15, Arabic), against the real API on the throwaway
database, with 8 weeks of generated history (about 3,000 tickets: busy late
mornings, closed Fridays, growing demand, one queue slower than its setting).
This history is test data for checking the feature; the code reads whatever
is in the database.

- **Screen:** metrics, recommendations, busiest and quietest times, the
  hourly chart and the coming week all render correctly in Arabic RTL.
- **Results match the pattern:** 10:00–12:00 flagged as the busiest hours,
  Friday closed, demand up, and "General consultation" taking 14 minutes
  against its 10-minute setting.
- **Speed:** about 0.5 s per request for 1,800 recent tickets on this
  machine.

**Found this way, and fixed with tests:**

1. "High" confidence could never be reached: the default history window holds
   20 tickets, and the threshold was 30. A full window now counts as high.
2. In Arabic, the list of busiest hours read as one left-to-right block with
   the commas on the wrong side. It now uses Arabic commas and a right-to-left
   mark. Confirmed on the phone after the fix.

Also corrected after looking at the screen: the average label gained its unit
("16.3 customers on average"). That text change is covered by the string
checks, but the phone still has the build from before it.

## Limitations

1. **Not machine learning or a language model.** Predictions are statistics
   and explicit rules. That keeps them explainable and free, but they
   do not learn seasonality beyond the weekday and hour pattern.
2. **Peak times assume next week resembles the last 8.** Holidays and one-off
   events are not modelled.
3. **"Active staff" is inferred from recent calls.** Someone present who has
   not called anyone in 45 minutes is not counted.
4. **Thresholds are fixed defaults.** They were chosen by judgment, not tuned
   on real businesses, and are not configurable per business. `dory_db` has
   no real business history yet, so no recommendation has been checked
   against real customers.
5. **Prediction accuracy has not been measured.** `estimated_wait_seconds` is
   stored at join time, so a later comparison with actual waits is possible.
6. **Insights use one window** (30 days) with no date picker, and the app
   shows the whole business. The API supports `?branch=`; the screen does not
   offer it yet.
7. **Analysis runs per request** (bounded and rate-limited), with no cache.
   That is fine at this size; very large businesses may want caching.
8. **Multi-time-zone businesses:** arrivals use each branch's zone, but the
   "next 7 days" use the first branch's zone.
9. iOS was not built or checked (it needs a Mac).

## Local changes outside the repository

- `dory_db`: unchanged in this phase.
- `dory_db_testing` held the generated history. The test suites reset it
  afterwards, as every run does.
- The MySQL 8.4 test server is stopped. Nothing listens on 8000, and
  `adb reverse` is removed.
- The phone has a debug build from before the label change, pointing at
  `127.0.0.1`. Its saved session belongs to the reset test database.

## Commits

All commits use the owner's identity with no co-author line, and every push
was verified. The repository remains private. No paid service was activated.

| Commit | Content |
|---|---|
| `3ea7fe1` | API: smart wait prediction, peak times, insights, tests |
| *(this commit)* | App: insights screen, wait range; documentation and this report |

## Next

Phase 08 has **not** been started and will not be without your explicit
approval.
