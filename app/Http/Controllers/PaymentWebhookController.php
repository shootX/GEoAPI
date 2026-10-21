<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Billing\PaymentEvents;
use App\Billing\StripeSignature;
use App\Billing\StripeSignatureException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            return response()->json([
                'error' => ['code' => 'billing_not_configured', 'message' => 'გადახდის პროვაიდერი არ არის მიერთებული.'],
            ], 503);
        }

        $signature = $request->header('Stripe-Signature');
        try {
            $event = StripeSignature::verify(
                $request->getContent(),
                is_string($signature) ? $signature : null,
                trim($secret),
            );
        } catch (StripeSignatureException $exception) {
            if ($exception->reason === StripeSignatureException::UNCONFIGURED) {
                return response()->json([
                    'error' => ['code' => 'billing_not_configured', 'message' => 'გადახდის პროვაიდერი არ არის მიერთებული.'],
                ], 503);
            }

            Log::warning('stripe.webhook.rejected', ['reason' => $exception->reason]);

            $malformed = $exception->reason === StripeSignatureException::MALFORMED;

            return response()->json([
                'error' => [
                    'code' => $malformed ? 'invalid_parameter' : 'invalid_signature',
                    'message' => $malformed ? 'შეტყობინების იდენტიფიკატორი არასწორია.' : 'ხელმოწერა არასწორია.',
                ],
            ], 400);
        }

        $fresh = PaymentEvents::record('stripe', $event['id'], $event['type'], [
            'type' => $event['type'],
        ]);

        return response()->json([
            'ok' => true,
            'duplicate' => ! $fresh,
        ]);
    }
}
