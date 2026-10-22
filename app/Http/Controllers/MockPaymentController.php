<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Billing\AuditLog;
use App\Billing\Entitlements;
use App\Billing\MockPay;
use App\Billing\PaymentEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Owner-approved TEST checkout. Collects no card data; see App\Billing\MockPay for the guard. */
final class MockPaymentController
{
    public function show(Request $request, string $order): View
    {
        $row = $this->order($request, $order);

        return view('billing.mock', ['order' => $row]);
    }

    public function complete(Request $request, string $order): RedirectResponse
    {
        $row = $this->order($request, $order);
        $result = $request->input('result') === 'success' ? 'completed' : 'rejected';
        DB::transaction(function () use ($row, $result, $request): void {
            if (! PaymentEvents::record(MockPay::PROVIDER, $row->public_id.':'.$result, 'order_payment', ['status' => $result, 'order' => $row->public_id])) {
                return;
            }
            AuditLog::write((int) $request->user()->id, 'billing.mock.'.$result, 'billing_order', (string) $row->public_id, ['provider' => MockPay::PROVIDER]);
            if ($result === 'completed') {
                // Same entry point a verified BOG callback uses: plan, entitlement, paid status, invoice PDF.
                Entitlements::grant((string) $row->public_id);
            } else {
                Entitlements::failOrder((string) $row->public_id);
            }
        });

        return redirect('/account/billing?paid='.($result === 'completed' ? 'test' : 'fail'));
    }

    private function order(Request $request, string $publicId): object
    {
        abort_unless(MockPay::enabled(), 404);
        $row = DB::table('billing_orders')->where('public_id', $publicId)->first();
        abort_if($row === null || (string) ($row->provider ?? '') !== MockPay::PROVIDER, 404);
        abort_unless((int) $row->user_id === (int) $request->user()->id, 403);
        abort_unless($row->status === 'pending', 410);

        return $row;
    }
}
