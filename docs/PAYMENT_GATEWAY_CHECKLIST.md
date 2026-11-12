# Payment gateway checklist

For the first real-bank test of online card payment (Mastercard Gateway
Hosted Checkout, docs/PAYMENTS.md). **Nothing here has been done.** No
merchant account, credential or test environment exists, and no charge has
ever been made. The integration is tested against a faked gateway only.

Live online payments stay **off** until the last step:
`BILLING_ONLINE_PAYMENTS=false` (the default) refuses every checkout with
`503 payments_unavailable`, even when credentials are set. Payments received
by transfer or cash keep working through the admin dashboard meanwhile.

## 1. Before contacting the bank

- [ ] The company that will sell DORY subscriptions exists, with the papers
      the bank asks for (registration, tax number, bank account).
- [ ] Terms of sale, refund policy and privacy policy are published (the
      bank checks them on the website).
- [ ] The public HTTPS domain for the API is decided (the bank needs the
      return and webhook addresses).
- [ ] Decided: the currencies to charge in, and the real prices
      (docs/COMMERCIAL_DECISIONS.md).

## 2. Ask the bank for

- [ ] A **test** merchant on the bank's test gateway, with test cards
      (success, decline, 3-D Secure challenge).
- [ ] The gateway address (`https://<bank>.gateway.mastercard.com`), the API
      version (DORY is written for version 100) and the authentication
      method (DORY uses the integration password).
- [ ] The currencies the bank settles, and their minor units.
- [ ] Merchant Administration access to set up webhook notifications.
- [ ] Which PCI DSS self-assessment applies (Hosted Checkout usually means
      the shortest one, SAQ A, because card details never reach DORY).
- [ ] Their settlement and reconciliation reports, and how refunds are made.

## 3. Credentials

- [ ] Test and production credentials are separate, and the production ones
      are requested only after the test sign-off (section 5).
- [ ] Credentials go **only** into `backend/.env` on the server, typed or
      pasted there by the person responsible. Never in the repository, a
      ticket, a chat, an email or a screenshot. `.env` is not committed
      (`.gitignore`) and not readable by other system users.
- [ ] Settings → *Server configuration* in the admin dashboard shows
      *Provider credentials: Set*. It never shows the values.
- [ ] Who may read the server's `.env` is written down; the API password is
      rotated in Merchant Administration if someone leaves, and after any
      suspected leak.

## 4. Configure the test environment

On a test server reachable from the internet over HTTPS (not `localhost`):

```
APP_URL=https://<test domain>
PAYMENT_PROVIDER=mastercard
MPGS_GATEWAY_URL=https://<bank test gateway>
MPGS_API_VERSION=100
MPGS_MERCHANT_ID=<test merchant>
MPGS_API_PASSWORD=<test password>
MPGS_NOTIFICATION_SECRET=<from Merchant Administration>
MPGS_MERCHANT_NAME=DORY
BILLING_ONLINE_PAYMENTS=true
```

- [ ] Webhook notifications point to
      `https://<test domain>/api/v1/billing/webhooks/mastercard`.
- [ ] Plans have prices in a currency the test merchant settles (admin
      dashboard → Plans).
- [ ] The scheduler runs (`php artisan schedule:run` every minute), so
      expired payment links are settled.
- [ ] `APP_DEBUG=false`.

## 5. Test with the bank's test cards

Record the date, the order id (DORY's checkout id) and the result of each.

| # | Case | Expected in DORY |
|---|---|---|
| 1 | Pay Pro monthly with a success card | Invoice paid and numbered, source *Verified online*; plan active until one month later; counted under *Verified online* in Revenue |
| 2 | Decline card | Checkout failed, invoice failed with the bank's reason; plan unchanged |
| 3 | 3-D Secure challenge, completed | Same as 1 |
| 4 | 3-D Secure challenge, failed or abandoned | Nothing paid; plan unchanged |
| 5 | Close the payment page without paying | Checkout expires after `BILLING_CHECKOUT_EXPIRES_MINUTES`; nothing paid |
| 6 | Pay, then open the return link again | Applied once; one invoice |
| 7 | The bank sends the same webhook twice | Processed once (`payment_webhook_events`) |
| 8 | A webhook with a wrong secret (sent by hand) | `401`; nothing changes |
| 9 | Stop the test server's internet during a payment, then restore | The webhook retry or the next status check applies it once |
| 10 | Pay Business yearly in a second currency, if offered | The amount and currency match the plan price exactly |
| 11 | Compare with the bank's order list | Every captured order has exactly one paid invoice with the same amount and currency, and nothing else is paid |
| 12 | Read `storage/logs` | No card number, password, secret or session id |

Check during the tests:

- [ ] The field names the bank's gateway returns match what DORY reads
      (`status`, `result`, `amount`, `currency`, `totalCapturedAmount`);
      version 100 renamed some fields, so compare with a real response.
- [ ] Amounts are sent with the currency's decimals (15.00 USD; three
      decimals where the currency has them).
- [ ] The order id DORY sends is the checkout id, and descriptions read well
      on the bank's page in English and Arabic.

## 6. Refunds and disputes (manual)

DORY has no refund call. During the pilot:

- [ ] A refund is made in Merchant Administration, then an admin ends or
      changes the business's plan in the dashboard, with a note.
- [ ] Chargebacks are followed in the bank's reports.

## 7. Sign-off and go live

- [ ] All cases in section 5 passed, written down with dates.
- [ ] The person responsible for the merchant account agrees in writing.
- [ ] Production credentials entered in the production server's `.env`
      (section 3), with the production gateway address and webhook.
- [ ] One small real payment, checked against the bank's report, then
      refunded or kept.
- [ ] Only then: `BILLING_ONLINE_PAYMENTS=true` in production, and
      `php artisan config:cache`.

**To stop online payments at once:** set `BILLING_ONLINE_PAYMENTS=false`
(and `php artisan config:cache`). Checkouts are refused, the app shows how to
pay by transfer or cash, and the admin dashboard keeps recording payments.

## 8. After going live

- [ ] Every day for the first month: the bank's settlement report against
      *Payments → Paid → Verified online*.
- [ ] Any checkout marked for review (paid, but the subscription changed
      meanwhile) is settled by an admin: refund at the bank, or give the plan.
- [ ] Failed webhook verifications and provider errors in the log are looked
      at the same day.
