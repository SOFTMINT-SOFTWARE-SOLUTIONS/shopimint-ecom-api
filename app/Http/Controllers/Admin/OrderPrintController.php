<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderPrintController extends Controller
{
    public function invoice(string $orderNumber)
    {
        $order = Order::with(['items', 'customer'])->where('order_number', $orderNumber)->firstOrFail();

        $pdf = Pdf::loadView('pdf.invoice', [
            'order' => $order,
        ])->setPaper('a4');

        return $pdf->download("invoice-{$order->order_number}.pdf");
    }

    public function shippingLabel(string $orderNumber)
    {
        $order = Order::with(['items', 'customer'])->where('order_number', $orderNumber)->firstOrFail();

        // A6 label is a common size. If you need 4x6 inches, tell me.
        $pdf = Pdf::loadView('pdf.shipping-label', [
            'order' => $order,
        ])->setPaper('a6', 'portrait');

        return $pdf->download("label-{$order->order_number}.pdf");
    }
}
