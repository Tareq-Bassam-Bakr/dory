# Commercial decisions

Four open questions about plans and pricing, with a recommendation for each.
**Nothing here has been changed.** Prices, limits, the trial and every
business's entitlements are exactly as Phase 08 left them, and they stay so
until you decide. Every value below can already be changed without a
deployment (admin dashboard → Plans and Settings), except where a decision
needs new code, which is marked.

What DORY has today:

| | Free | Pro | Business |
|---|---|---|---|
| Branches | 1 | 3 | unlimited |
| Queues | 3 | 15 | unlimited |
| Team seats (owner and pending invitations included) | 3 | 10 | unlimited |
| Insights | – | ✓ | ✓, also per branch |
| Price, monthly / yearly | 0 | 15 / 150 USD | 49 / 490 USD |

New businesses try Pro for 14 days, once per owner; after an unpaid period a
paid plan stays 7 grace days. Customers never pay, whatever the plan.

## 1. Several Free businesses under one owner

**Today.** Plans belong to the business. One person can create several
businesses, and each starts on Free (after the one trial). Three Free
businesses give an owner 3 branches, 9 queues and 9 seats at no cost: more
than the Free plan is meant to give, and close to Pro.

**Options.**

| | Option | For | Against |
|---|---|---|---|
| A | Keep it (one Free plan per business) | Simplest; nothing to explain; genuine separate businesses (a clinic and a pharmacy) are each served | Easy to split one business into several to avoid paying |
| B | **One automatic Free business per owner**; an owner's further businesses start without plan features until a paid plan or an admin's approval | Stops splitting; real cases (separate companies, franchises) still get Free by approval | New code; an owner sees a "pending" state for a second business |
| C | Free limits shared across all of an owner's businesses | Fair to the letter | Complex to explain and to enforce (whose business counts first?); a business's limits would change when another one is created |
| D | Admin review of every new business (the new *Discovery needs approval* setting) | Already built; also screens fake businesses | Slows every honest sign-up; does not limit Free use, only visibility |

**Recommendation: B**, with the admin approval already built for the
exceptions. Ownership changes and team members are not affected: the rule
counts businesses where the person is the *owner*. It needs new code (about
a day, with tests), and existing businesses keep what they have.

## 2. Final prices and currency

**Today.** Placeholders in USD: Pro 15 USD a month, Business 49 USD a month,
a year for the price of 10 months (16% saving).

**Things to weigh.**

- What a small business in the first market can pay each month, compared
  with what one lost customer costs it. A queue tool pays for itself when it
  saves staff time at the counter.
- **Currency:** USD keeps prices stable; a local currency is easier to pay
  but needs price updates when the exchange rate moves. DORY supports both:
  a plan can have prices in several currencies, and Settings → *Accepted
  currencies* controls which ones admins can use.
- **What the bank settles**, once online payment exists
  (docs/PAYMENT_GATEWAY_CHECKLIST.md). Until then payments are by transfer or
  cash, in whatever currency is agreed.
- Prices shown in the app are the plan's prices; an admin recording a
  payment can enter a different amount (a discount) for that payment only.

**Recommendation.**

1. Keep **USD as the reference currency** for list prices, and add the local
   currency to *Accepted currencies* only with a fixed local price per plan
   that an admin reviews regularly (for example every month), rather than a
   live exchange rate.
2. Decide the amounts from 5 to 10 conversations with target businesses
   during the pilot, starting from the placeholders (they put Pro at about a
   third of Business, so moving up is a clear step).
3. Keep yearly at 10 months' price.
4. Offer pilot businesses a complimentary plan for a fixed period (admin
   dashboard → *Give a plan*, with an end date) instead of a lower list
   price; it ends by itself and never counts as revenue.

## 3. Trial length and plan limits

**Today.** Pro for 14 days, once per owner; limits as in the table above.

**Recommendation.**

- **Keep 14 days** as the default, and extend case by case: an admin can
  give or extend a trial of up to 90 days from the dashboard. A business
  usually needs about a week to set up branches, queues and staff, then a
  week of real customers to see the value. Consider 30 days only if the
  pilot shows most businesses are not set up by day 7 (Settings → *Trial
  days*, no deployment).
- **Free:** keep 1 branch and 3 queues. Consider **2 seats instead of 3**
  only together with decision 1, because seats are what grow with a real
  business; otherwise keep 3.
- **Pro:** keep 3 branches, 15 queues, 10 seats.
- **Business:** keep unlimited, and watch for unusual use in the overview
  (businesses per plan) rather than adding a cap now.
- Lowering a limit never removes anything from a business; it only stops
  adding more. Raising one applies at once.

## 4. The free plan policy for businesses

**Today.** Every business that is not paying (and not in a trial) has the
Free plan, for as long as it likes. The customer experience is the same on
every plan.

**Recommendation.** Keep **Free per business, without a time limit**, and
keep everything customers see (joining, live updates, notifications,
discovery) free on every plan. A queue tool spreads through the customers
who use it, and a free plan that works is how businesses meet DORY. Combine
it with decision 1 (one automatic Free business per owner), and use the
*Discovery needs approval* setting only if fake or spam businesses appear.

## Decisions needed

| # | Question | Recommendation | Needs code |
|---|---|---|---|
| 1 | Several Free businesses per owner | One automatic Free business per owner; more by admin approval | Yes |
| 2 | Prices and currency | USD reference; local currency at fixed reviewed prices; amounts from pilot conversations | No |
| 3 | Trial and limits | Keep 14 days and the current limits; adjust after the pilot | No |
| 4 | Free plan policy | Free per business, no time limit; customers never pay | No |
