<?php

namespace App\Http\Controllers\Web;

use App\Contracts\PaymentGateway;
use App\Enums\CheckoutStatus;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Models\PaymentCheckout;
use App\Services\Payments\CheckoutService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The two pages around an online payment (docs/PAYMENTS.md):
 *
 * - pay: opened from the app through a signed, expiring link; hands the
 *   payer to the provider's hosted payment page. Card details never touch
 *   DORY.
 * - return: where the provider sends the payer back. It asks the provider
 *   what happened (never trusting the URL) and says so.
 */
class BillingCheckoutPageController extends Controller
{
    public function pay(Request $request, string $checkout, PaymentGateway $gateway): Response
    {
        $found = $this->find($checkout);
        if ($found->status !== CheckoutStatus::Pending || $found->expires_at->isPast()) {
            return $this->result($found->status === CheckoutStatus::Pending ? 'expired' : $found->status->value, $found);
        }

        try {
            $page = $gateway->pageData($found);
        } catch (BillingException) {
            return $this->result('unavailable', $found);
        }

        return response()
            ->view('billing.pay', [
                'checkout' => $found,
                'page' => $page,
                'amount' => Money::decimal($found->amount_minor, $found->currency),
                'returnUrl' => route('billing.checkout.return', ['checkout' => $found->public_id, 'lang' => app()->getLocale()]),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function return(Request $request, string $checkout, CheckoutService $checkouts, PaymentGateway $gateway): Response
    {
        $found = $this->find($checkout);
        $returned = $gateway->acceptsReturn($found, $request);

        try {
            $found = $checkouts->finalize($found);
        } catch (BillingException) {
            // The provider could not be reached: the webhook or the scheduler
            // settles the payment; say it is being confirmed.
            return $this->result('pending', $found);
        }

        $status = $found->status->value;
        if ($found->status === CheckoutStatus::Pending) {
            $status = $returned ? 'confirming' : 'pending';
        }

        return $this->result($status, $found);
    }

    private function find(string $publicId): PaymentCheckout
    {
        return PaymentCheckout::query()->with('plan')->where('public_id', $publicId)->first()
            ?? abort(404);
    }

    private function result(string $status, PaymentCheckout $checkout): Response
    {
        return response()
            ->view('billing.result', [
                'status' => $status,
                'checkout' => $checkout,
                'amount' => Money::decimal($checkout->amount_minor, $checkout->currency),
            ])
            ->header('Cache-Control', 'no-store');
    }
}
