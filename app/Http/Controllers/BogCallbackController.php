<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Billing\BogSignature;
use App\Billing\CallbackMismatch;
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
        $refundId = '';
        if ($status === 'refunded_partially') {
            $partialUnits = is_array($body['purchase_units'] ?? null) ? $body['purchase_units'] : [];
            $partialRaw = $partialUnits['refund_amount'] ?? null;
            $candidate = $body['refund_id'] ?? null;
            if (is_string($candidate) && preg_match('/^[A-Za-z0-9:_-]{1,80}$/', $candidate) === 1) {
                $refundId = $candidate;
            }
            $eventId .= $refundId !== ''
                ? ':id:'.$refundId
                : ':'.(is_numeric($partialRaw) ? number_format((float) $partialRaw, 2, '.', '') : 'invalid');
        }
        $apply = function () use ($eventId, $status, $externalId, $body, $bogOrderId, $refundId): string {
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
                $units = is_array($body['purchase_units'] ?? null) ? $body['purchase_units'] : [];
                $currency = $units['currency_code'] ?? 'GEL';
                if ($currency !== 'GEL') {
                    Log::warning('bog.callback.mismatch', ['order' => $externalId]);

                    return 'mismatch';
                }
                $refundRaw = $units['refund_amount'] ?? null;
                if ($status === 'refunded_partially') {
                    if (! is_numeric($refundRaw)) {
                        Log::warning('bog.callback.mismatch', ['order' => $externalId]);

                        return 'mismatch';
                    }
                    $amount = number_format((float) $refundRaw, 2, '.', '');
                    $recorded = $refundId !== ''
                        ? Entitlements::recordDistinctPartial($externalId, $amount, $eventId)
                        : Entitlements::recordPartialRefund($externalId, $amount);
                    if (bccomp($amount, '0.00', 2) !== 1 || ! $recorded) {
                        Log::warning('bog.callback.mismatch', ['order' => $externalId]);

                        return 'mismatch';
                    }

                    return 'partial';
                }
                if ($refundRaw !== null && $refundRaw !== '') {
                    $amount = is_numeric($refundRaw) ? number_format((float) $refundRaw, 2, '.', '') : '';
                    $expected = number_format((float) $order->gel_amount, 2, '.', '');
                    if ($amount === '' || bccomp($amount, $expected, 2) !== 0) {
                        Log::warning('bog.callback.mismatch', ['order' => $externalId]);

                        return 'mismatch';
                    }
                    Entitlements::refund($externalId, $amount);

                    return 'refunded';
                }
                Entitlements::refund($externalId);

                return 'refunded';
            }

            return 'recorded';
        };

        try {
            $result = DB::transaction(static function () use ($apply): string {
                $result = $apply();
                if ($result === 'mismatch') {
                    // Roll back the event row as well: a body that does not match the order must not mark
                    // order:status as handled, or the correct callback is later dropped as a duplicate (GEO-QA-007).
                    throw new CallbackMismatch;
                }

                return $result;
            });
        } catch (CallbackMismatch) {
            return response()->json(['ok' => false, 'error' => ['code' => 'mismatch', 'message' => 'შეტყობინება შეკვეთას არ ემთხვევა.']]);
        }

        return response()->json(['ok' => true, 'duplicate' => $result === 'duplicate']);
    }
}
