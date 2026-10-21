<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Billing\BogSignature;
use App\Billing\Entitlements;
use App\Billing\PaymentEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class BogCallbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('Callback-Signature');
        if (! BogSignature::verify($raw, is_string($signature) ? $signature : null)) {
            Log::warning('bog.callback.rejected', ['reason' => 'signature']);

            return response()->json([
                'error' => ['code' => 'invalid_signature', 'message' => 'ხელმოწერა არასწორია.'],
            ], 400);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json([
                'error' => ['code' => 'invalid_parameter', 'message' => 'შეტყობინების სხეული არასწორია.'],
            ], 400);
        }

        $body = $payload['body'] ?? null;
        $status = is_array($body) ? ($body['order_status']['key'] ?? null) : null;
        $bogOrderId = is_array($body) ? ($body['order_id'] ?? null) : null;
        $externalId = is_array($body) ? ($body['external_order_id'] ?? null) : null;
        if (($payload['event'] ?? '') !== 'order_payment' || ! is_string($status) || $status === '' || ! is_string($bogOrderId) || $bogOrderId === '') {
            return response()->json([
                'error' => ['code' => 'invalid_parameter', 'message' => 'შეტყობინების იდენტიფიკატორი არასწორია.'],
            ], 400);
        }

        $eventId = $bogOrderId.':'.$status;
        $result = DB::transaction(function () use ($eventId, $status, $externalId, $body, $bogOrderId): string {
            $fresh = PaymentEvents::record('bog', $eventId, 'order_payment', [
                'status' => $status,
                'order' => is_string($externalId) ? $externalId : null,
            ]);
            if (! $fresh) {
                return 'duplicate';
            }
            if (! is_string($externalId) || $externalId === '') {
                return 'ignored';
            }
            $order = DB::table('billing_orders')->where('public_id', $externalId)->first();
            if ($order === null) {
                return 'unknown';
            }
            if ($status === 'completed') {
                // The signed body must describe this order: same BOG id and the GEL amount we asked for.
                $units = is_array($body['purchase_units'] ?? null) ? $body['purchase_units'] : [];
                $paid = $units['request_amount'] ?? $units['transfer_amount'] ?? null;
                $currency = $units['currency_code'] ?? 'GEL';
                $sameOrder = $order->bog_order_id === null || (string) $order->bog_order_id === $bogOrderId;
                $sameAmount = is_numeric($paid) && bccomp(number_format((float) $paid, 2, '.', ''), (string) $order->gel_amount, 2) === 0;
                if (! $sameOrder || ! $sameAmount || $currency !== 'GEL') {
                    Log::warning('bog.callback.mismatch', ['order' => $externalId]);

                    return 'mismatch';
                }
                Entitlements::grant($externalId);

                return 'granted';
            }
            if ($status === 'refunded' || $status === 'refunded_partially') {
                Entitlements::refund($externalId);

                return 'refunded';
            }

            return 'recorded';
        });

        return response()->json(['ok' => true, 'duplicate' => $result === 'duplicate']);
    }
}
