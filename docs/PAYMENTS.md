# Payments

**No real payment works yet.** No payment provider account exists, no
credentials were assumed, and nothing has been charged. What exists:

| | Status |
|---|---|
| Payments received outside DORY (bank transfer, cash, mobile wallet), recorded by a platform admin | **Works now.** The plan starts at once, with a numbered invoice ([admin API](SUBSCRIPTIONS.md#admin-api)) |
| Online card payment through Mastercard Gateway (Hosted Checkout) via a Syrian acquiring bank | **Built, not live.** Tested against a faked gateway only; it needs a merchant account from a bank |
| Any other provider | Not built. The design has a place for one ([Adding a provider](#adding-a-provider)) |

Plans, limits and the subscription lifecycle: [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md).

## Choosing a provider

Researched on 2026-11-11. DORY's businesses are in Syria (addresses,
country `SY`, Arabic first), and that decides most of the choice.

| Provider | Kind | Syria | Verdict |
|---|---|---|---|
| Stripe | Card processor | Not on Stripe's list of supported countries. Its restricted-business rules bar dealings with people located in, resident in or citizens of Syria (secondary sources: [Zenind][stripe-zenind], [StartupOwl][stripe-owl], [Conduct Atlas][stripe-rules]) | Not usable for Syrian businesses, or for DORY if it is run from Syria |
| Paddle | Merchant of record (handles sales tax) | Listed as unsupported; sign-ups and sales from sanctioned or restricted countries are blocked ([Paddle help][paddle-help], [Paddle developer docs][paddle-dev]) | Not usable |
| Polar | Merchant of record | "Payments globally except from countries with US sanctions", Syria included ([Polar docs][polar]) | Not usable |
| **Mastercard Gateway** through a Syrian acquiring bank | Gateway run by the bank | Launched for Syrian banks and merchants, in-store and online through local banks ([The Paypers][mpgs-syria]) | **Recommended for online payment** |
| Manual (transfer, cash, mobile wallet) | None | Works | **Available now** |

What changed in Syria in 2026, as reported:

- **4 May:** the Central Bank of Syria let licensed banks and electronic
  payment companies work with international payment firms ([Enab
  Baladi][cbs-may], [The New Arab][newarab-shift]).
- **9 May:** the first Visa and Mastercard trial payment in Damascus in more
  than 15 years, with a plan for 50,000 payment devices by the end of 2026
  ([Enab Baladi][trial-may]).
- **August:** Mastercard and QNB processed Syria's first international card
  payment in over 15 years ([The Paypers][mpgs-syria],
  [Vermögenszentrum][vz-first]).
- **September:** the first locally issued card accepted internationally
  ([Anadolu][aa-card]).

**Caveats.** Cards may still not work everywhere ([Syrian
Guides][guide-caveat]), and the infrastructure is still limited ([The New
Arab][newarab-economy]). I found no source on whether recurring online
billing works yet. Provider country lists follow sanctions, which have
been changing: check each provider's current terms before deciding. This
is not legal advice; for a business with ties to Syria, a compliance
review is worth it.

**Not evaluated:** PayPal, other processors, and the APIs of local mobile
wallets. No research was done on them in this phase.

### Recommendation

1. **Now:** manual payments recorded by a platform admin. No provider, no
   credentials, no cost.
2. **Online:** Mastercard Gateway Hosted Checkout, through a Syrian
   acquiring bank that offers it. The integration is built; it needs the
   bank's merchant account ([Setting it up](#setting-up-mastercard-gateway)).
3. **If DORY later sells to businesses outside Syria through a company
   outside Syria:** a merchant of record such as Paddle, as a second
   provider. It takes care of sales tax. Not built.

## How an online payment works

```
 App (owner)            DORY API                     Bank (Mastercard Gateway)
  │ choose plan ──────► POST …/subscription/checkout
  │                     price from the database ──────► INITIATE_CHECKOUT
  │                     keeps session, hashed success ◄── session id, success indicator
  │ ◄── signed link, valid 60 min
  │ opens it in the browser ─► /billing/checkout/{id}/pay ─► bank's payment page
  │                                                         (card details entered there only)
  │                     /billing/checkout/{id}/return ◄───── payer sent back
  │                     asks the bank (Retrieve Order) ────►
  │                     webhook (secret checked, once) ◄──── notification
  │                     asks the bank again ───────────────►
  │ back in the app: GET …/checkouts/{id} (asks the bank if still open)
```

The return page, the webhook, the app and the scheduler all end in one
method, `CheckoutService::finalize`. It asks the bank, then applies the
answer **once**, under a row lock. Calling it again, in any order, changes
nothing more.

A payment counts as paid only when **the server itself** retrieved the order
from the bank with status `CAPTURED` and the **exact amount and currency**
of the checkout. The browser's return and the webhook are only reasons to
ask.

| The bank says | DORY does |
|---|---|
| Captured, right amount | The plan is paid ([lifecycle](SUBSCRIPTIONS.md#lifecycle)), with an invoice |
| Captured, another amount | Refused: failed invoice (`amount_mismatch`), logged as an error for review. The plan does not change |
| Failed, cancelled, authentication failed | Failed invoice. The plan does not change |
| Refunded, chargeback | Not paid |
| Nothing yet | Stays pending; expires after its link (plus 15 minutes) |
| Captured after the link expired, or after an earlier check failed | Still applied: money taken is never ignored |
| Captured, but the subscription changed meanwhile so it cannot apply (for example a bigger plan bought in another checkout) | Kept as `needs_review` and logged. A platform admin settles it (refund at the bank, or assign the plan) |

The amount always comes from the plan's price in the database, never from
the app.

### Webhooks

`POST /api/v1/billing/webhooks/mastercard`, no account.

- The `X-Notification-Secret` header must equal `MPGS_NOTIFICATION_SECRET`
  (constant-time comparison). Anything else is `401 invalid_notification`
  ([Mastercard: webhook notifications][mpgs-webhooks]).
- `X-Notification-Id` is stored once in `payment_webhook_events`. A repeated
  notification is answered `duplicate` and does nothing.
- The body is not trusted for the payment's state. Only the order id is read,
  plus a few fields kept for support (order status, result, transaction type,
  gateway code, attempt). No card data is stored.
- An unknown order is acknowledged as `ignored`, so the bank stops retrying.
- If the bank cannot be reached to verify, the notification is kept as
  `failed` and answered `502`, so the bank sends it again.

## Security

- **No card data at DORY.** Cards are entered on the bank's hosted page only,
  which keeps DORY's servers out of card handling. Ask the bank which PCI DSS
  self-assessment applies.
- **Credentials only in `backend/.env` on the server**, never in the
  repository (`.env.example` has empty placeholders). Calls to the bank use
  HTTPS with basic authentication (`merchant.<id>`); a gateway URL that is
  not `https://` counts as not configured.
- **The payment link** is signed and expires with the checkout; only the
  owner can start a payment; a checkout is only found within its own
  business.
- **The success indicator is stored as a SHA-256 hash**, and the provider
  session id is never returned by the API.
- **Rate limits:** starting and checking payments 10 per minute per user;
  webhooks 120 per minute per IP; payment pages 30 per minute per IP.
- **Logs** hold the HTTP status and the bank's error cause only, never
  credentials or payer data (a test checks this).
- **The payment pages** are not cached (`no-store`), not indexed, and send no
  referrer.

## Setting up Mastercard Gateway

When a bank offers a merchant account:

1. Ask the bank for:
   - the gateway address, for example `https://<bank>.gateway.mastercard.com`;
   - a merchant id and an API password (integration authentication);
   - a test environment with a test merchant and test cards;
   - the currencies it settles (USD, SYP) and the API version.
2. In `backend/.env` on the server only:

   ```
   PAYMENT_PROVIDER=mastercard
   MPGS_GATEWAY_URL=https://<bank>.gateway.mastercard.com
   MPGS_API_VERSION=100
   MPGS_MERCHANT_ID=<from the bank>
   MPGS_API_PASSWORD=<from the bank>
   MPGS_NOTIFICATION_SECRET=<from Merchant Administration>
   MPGS_MERCHANT_NAME=DORY
   ```

3. In the bank's Merchant Administration, turn on webhook notifications to
   `https://<your domain>/api/v1/billing/webhooks/mastercard` and copy the
   notification secret. It needs a public HTTPS address, not `localhost`.
4. Set `APP_URL` to the public HTTPS address: the payment link and the return
   address are built from it.
5. Set prices in the currencies the bank settles (admin API).
6. Test on the bank's test environment first: a paid, a declined and an
   abandoned payment, and a webhook. Field names differ between API versions
   (the move to version 100 changed some
   ([Mastercard: v100 migration][mpgs-v100])), so check them against what the
   bank's gateway returns. Then switch to the production credentials.

Until then `PAYMENT_PROVIDER=none`: starting a checkout answers
`503 payments_unavailable`, and the app explains how to pay by transfer,
cash or mobile wallet.

## Recording a payment received outside DORY

After confirming the money arrived, a platform admin records it:
`POST /api/v1/admin/billing/businesses/{id}/payments` with the plan, period
and method ([admin API](SUBSCRIPTIONS.md#admin-api)). The same rules apply
as online: renewal, upgrade credit, trial days kept. A transfer reference
can be recorded only once.

## Adding a provider

Implement `App\Contracts\PaymentGateway` (`name`, `isConfigured`, `start`,
`verify`, `notification`, `acceptsReturn`, `pageData`) and select it with
`PAYMENT_PROVIDER` in `AppServiceProvider`. Checkouts, invoices, webhooks,
the payment pages and the plans stay as they are.

## What is verified

**Tested** (`tests/Feature/Billing/MastercardPaymentTest.php`, against a
faked gateway; no request leaves the tests):

- the session request (operation, amount, currency, return address) and its
  authentication;
- the signed, expiring payment page;
- a return without a captured order changes nothing;
- a captured payment is applied once;
- a different captured amount is refused;
- a declined payment is recorded;
- a payment captured after the link expired still counts;
- the webhook secret, a repeated notification, an unknown order, and a
  retry after the bank was unreachable;
- the logs never contain the secrets.

**Not verified:** any real gateway, real cards or money, the bank's exact
field names for its API version, 3-D Secure, refunds, settlement, and
recurring charges.

[stripe-zenind]: https://www.zenind.com/help/post/how-to-open-a-stripe-account-in-syria-what-works-what-doesn-t-and-the-compliant-u-s-route
[stripe-owl]: https://startupowl.com/setup/stripe-unsupported-countries
[stripe-rules]: https://conductatlas.com/platform/stripe/stripe-restricted-businesses-list/dealings-with-high-risk-jurisdictions-prohibited/
[paddle-help]: https://www.paddle.com/help/legal/sanctions/which-countries-are-supported-by-paddle
[paddle-dev]: https://developer.paddle.com/concepts/sell/supported-countries-locales
[polar]: https://polar.sh/docs/merchant-of-record/supported-countries
[mpgs-syria]: https://thepaypers.com/payments/news/mastercard-launches-mastercard-gateway-in-syria
[cbs-may]: https://english.enabbaladi.net/archives/2026/05/syrias-central-bank-allows-banks-to-work-with-global-payment-companies/
[newarab-shift]: https://www.newarab.com/news/syria-lets-banks-use-visa-mastercard-major-payment-shift
[trial-may]: https://english.enabbaladi.net/archives/2026/05/syria-tests-visa-and-mastercard-payments-after-15-years/
[vz-first]: https://www.vermoegenszentrum.ch/en/stock-exchange-and-markets/news/visa-mastercard-begin-first-syria-global-card-payments-years
[aa-card]: https://aa.com.tr/en/middle-east/syria-launches-1st-locally-issued-internationally-accepted-payment-card/4056252
[guide-caveat]: https://syrianguides.com/?p=8857
[newarab-economy]: https://www.newarab.com/news/visa-mastercard-arrive-syria-will-help-economy
[mpgs-webhooks]: https://developer.mastercard.com/mastercard-gateway/documentation/gateway-features/webhook-noti/
[mpgs-v100]: https://developer.mastercard.com/mastercard-gateway/documentation/tutorials-and-guides/migration-guides/mig-hosted-checkout-v100/
