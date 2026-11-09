# AI architecture

Phase 07 adds three capabilities. Each is computed by DORY from the business's
own records, with explicit rules, and works offline at no cost:

- smart wait-time prediction;
- peak-time forecasting;
- business insights.

No result is invented. When there is too little data, the API and the app say
so instead of guessing. The architecture leaves a clear place for a language
model or an external service later, without requiring one now.

## Layers

```
records (queue_tickets, queue_sessions)
   │  ActivityHistory: tenant scope, branch time zones, only needed columns
   ▼
analysis                       wording                     delivery
SmartWaitTimeEstimator ──────────────────────────────────► ticket / queue APIs
PeakTimeForecaster ──────────────────────────────────────► GET …/peak-times
BusinessInsights ──► Insight (kind, severity, facts) ──► InsightNarrator ──► GET …/insights
```

| Piece | Contract | Default | Replaceable by |
|---|---|---|---|
| Wait-time estimate | `App\Contracts\WaitTimeEstimator` | `SmartWaitTimeEstimator` | e.g. a trained model, with the same `estimate()` / `warm()` |
| Wording of insights | `App\Contracts\InsightNarrator` | `TemplateInsightNarrator` (`lang/{en,ar}/insights.php`) | a language-model narrator, selected with `DORY_AI_NARRATOR` |
| Peak times, insight rules | concrete services | `PeakTimeForecaster`, `BusinessInsights` | a forecasting model behind a new contract |

Settings: `config/dory.php`, `intelligence`.

### Adding a language model later

1. Implement `InsightNarrator`, for example `LlmInsightNarrator`, with an
   HTTP client to the chosen provider.
2. Send only the insight's **facts**: aggregated numbers, hours and queue
   names. Never customer names, phone numbers, tokens or ids. The facts are
   built that way on purpose.
3. Fall back to `TemplateInsightNarrator` on any error or timeout, so
   insights never fail because of the provider.
4. Bind it in `AppServiceProvider` when `DORY_AI_NARRATOR` names it. Keep its
   key in `.env` only, never in the repository.
5. A paid provider needs the owner's approval first.

The rules, thresholds and numbers stay in DORY. A model may only reword
them, so recommendations remain explainable and testable.

## Wait-time prediction

[WAIT_TIME_ESTIMATION.md](WAIT_TIME_ESTIMATION.md). It uses history, service
duration, the customers already at the counter, and the staff actually
serving. It gives a range and a confidence.

## Peak times

`GET /api/v1/businesses/{business}/peak-times[?branch={id}]`

- **Window:** the last `history_days` (56) days of arrivals, in each
  branch's own time zone.
- **Averages:** the mean customers per weekday and hour, over the days
  observed, closed days counted as zero.
- **Levels:** "busy" (at or above the 75th percentile of the business's own
  active hours), "quiet" (at or below the 25th), "normal", or "closed"
  (nobody ever came then).
- **Response:** the 7 × 24 averages and levels, the three busiest and
  quietest hours, and the next 7 days with expected customers and peak
  hour.
- **Minimum:** below 50 tickets or 14 days, `status: insufficient_data`,
  with the counts and minimums, and nothing forecast.

## Business insights

`GET /api/v1/businesses/{business}/insights[?branch={id}]`

The last `insight_days` (30) days, compared with the 30 before.

- **Metrics:** customers, served, completion rate, missed turns (no-shows /
  called), customer cancellations, average and median wait (joined →
  called), average service (started → completed, clamped like wait times),
  change in customers.
- **Recommendations** (each with the numbers that triggered it):

  | Kind | Severity | Rule |
  |---|---|---|
  | `no_show_high` | warning | at least 10 called, 15% or more missed their turn |
  | `long_waits` | warning | average wait 30 minutes or more (names the busiest hour) |
  | `cancellations_high` | warning | 20% or more cancelled by customers |
  | `peak_concentration` | suggestion | the 3 busiest hours bring 45% or more of customers |
  | `demand_up` / `demand_down` | suggestion / warning | at least 25% more or fewer customers than the previous period (which had at least 20) |
  | `service_time_drift` | suggestion | a queue's median service time (10+ visits) differs from its setting by 30% and 2 minutes or more |
  | `running_well` | positive | no warning, 80% or more served, average wait 15 minutes or less |

- **Minimum:** below 20 tickets in the period, `status: insufficient_data`.
  The metrics are shown, and no recommendation is made.
- **Language:** `Accept-Language` (English or Arabic), else the account
  language. `generated_by` says which narrator worded them (`template`).

## Security and privacy

- **Access:** owners and managers (`BusinessAbilities::canViewInsights`,
  exposed as `permissions.view_insights`).
  - Employees get 403.
  - Non-members get 404, as for every business route.
- **Branch scope:** a manager of selected branches sees only those
  branches. Another branch, or one of another business, is
  `branch_not_found`.
- **Data:** only times, statuses and cancel reasons are read. The responses
  hold no names, phone numbers, tokens or user ids (tested).
- **Load:** rate-limited (30 per minute per user), and at most `max_rows`
  (50,000) tickets per request.

## Tests

- `tests/Feature/Intelligence/SmartWaitTimeEstimatorTest.php`
- `tests/Feature/Intelligence/BusinessIntelligenceApiTest.php`: rules,
  thresholds, local time, insufficient data, Arabic, privacy, tenant and
  branch isolation.
- `test/features/analytics/business_insights_test.dart`
- `test/features/tickets/ticket_wait_range_test.dart`
