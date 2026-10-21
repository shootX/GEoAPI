<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class InvoiceController extends Controller
{
    public function __invoke(int $id): Response
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $invoice = DB::table('billing_invoices')->where('id', $id)->first();
        abort_unless($invoice !== null, 404);
        $order = DB::table('billing_orders')->where('id', $invoice->billing_order_id)->first();
        abort_unless($order !== null && (int) $order->user_id === (int) $user->id, 404);

        $pdf = $invoice->pdf;
        if (is_resource($pdf)) {
            $pdf = stream_get_contents($pdf) ?: '';
        }

        return response((string) $pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"',
        ]);
    }
}
