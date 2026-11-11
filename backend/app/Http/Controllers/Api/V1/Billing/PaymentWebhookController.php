<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Services\Payments\WebhookProcessor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /billing/webhooks/{provider}: payment provider notifications
 * (docs/PAYMENTS.md, "Webhooks"). No account: the provider authenticates
 * with its shared secret. A 2xx tells the provider to stop retrying.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, WebhookProcessor $processor): JsonResponse
    {
        return ApiResponse::success(
            ['result' => $processor->handle($provider, $request)],
            __('api.billing.webhook_received'),
        );
    }
}
