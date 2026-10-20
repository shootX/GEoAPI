<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Billing\PaymentEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret', '');
        if ($secret === '') {
            return response()->json([
                'error' => ['code' => 'billing_not_configured', 'message' => 'გადახდის პროვაიდერი არ არის მიერთებული.'],
            ], 503);
        }

        $eventId = $request->input('id');
        $type = $request->input('type');
        if (! is_string($eventId) || $eventId === '' || ! is_string($type) || $type === '') {
            return response()->json([
                'error' => ['code' => 'invalid_parameter', 'message' => 'შეტყობინების იდენტიფიკატორი არასწორია.'],
            ], 422);
        }

        $fresh = PaymentEvents::record('stripe', $eventId, $type, [
            'type' => $type,
        ]);

        return response()->json([
            'ok' => true,
            'duplicate' => ! $fresh,
        ]);
    }
}
