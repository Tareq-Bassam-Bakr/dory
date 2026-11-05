# Business Onboarding

A five-step flow that creates a business, its main branch and its first
services in **one** API request.

| Step | Collects | Required |
|---|---|---|
| 1. Name and category | name, category (from the API) | both |
| 2. Description and contact | description, email, phone, website | none |
| 3. Main branch | name (pre-filled "Main branch"), address, city, region, country, latitude/longitude, phone, email, time zone | branch name |
| 4. Services | any number of services: name + duration (default 15 min) | none |
| 5. Review | summary with an Edit link per step | — |

## Behaviour

- **Progress**: "Step n of 5" plus a progress bar in the app bar.
- **Validation per step**: Next validates only the visible step, with the
  same rules as the API (`business_validators.dart`); the server validates
  again.
- **Data is kept** when moving back and forth: the draft lives in
  `OnboardingController` (Riverpod) and text controllers stay alive for the
  whole flow.
- **Leaving**: the close button and the system back button on step 1 ask
  to discard the draft once something was entered; on later steps back
  returns to the previous step.
- **No duplicates**: the Create button is disabled while submitting, and
  every onboarding session sends a random `Idempotency-Key`. If a request
  times out after the server saved the business, retrying returns the same
  business instead of creating a second one.
- **Server errors**: a field error sends the user back to the step that
  holds the field (for example `branch.latitude` → step 3) with the message
  under the field.
- **Atomic**: the server saves the business, owner membership, branch and
  services in one transaction (see
  [BUSINESS_MANAGEMENT.md](BUSINESS_MANAGEMENT.md#creation-and-ownership)).
- On success the app opens the new business's overview and refreshes the
  user's memberships, so the next app start lands in the business workspace.

## Files

| File | Role |
|---|---|
| `features/businesses/presentation/pages/create_business_page.dart` | Entry screen listing the steps |
| `features/businesses/presentation/pages/business_onboarding_page.dart` | The steps |
| `features/businesses/presentation/providers/onboarding_controller.dart` | Draft, current step, submission, idempotency key |
| `features/businesses/domain/entities/business_draft.dart` | `BusinessDraft`, `BranchDraft`, `ServiceDraft` |
| `features/businesses/data/models/business_models.dart` | Builds the request body (blank optional fields omitted) |
| `features/branch_management/presentation/widgets/branch_form_fields.dart` | Branch fields shared with the branch form |

## Tests

`test/features/businesses/presentation/business_onboarding_test.dart`:
required fields on step 1, data kept across steps, invalid optional input
blocks a step, the full flow (one POST despite a double tap, body and
`Idempotency-Key` checked, ends on the overview), and server errors
returning to the right step. `integration_test/phase03_e2e_test.dart` runs
the flow on a device against a real API.
