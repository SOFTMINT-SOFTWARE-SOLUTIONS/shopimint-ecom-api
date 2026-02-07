<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Services\InventoryReservationService;
use App\Services\OnePayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnePayController extends Controller
{
    public function status(Request $request, string $orderNumber, OnePayService $onepay, InventoryReservationService $inv)
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $intent = PaymentIntent::where('order_id', $order->id)
            ->latest()
            ->first();

        if (!$intent || !$intent->gateway_reference) {
            return response()->json(['message' => 'No OnePay transaction found for this order'], 404);
        }

        // Poll OnePay
        $resp = $onepay->getTransactionStatus($intent->gateway_reference);

        // ⚠️ OnePay status field names can differ. We store raw response and map safely.
        $status = strtolower((string)($resp['data']['status'] ?? $resp['status'] ?? ''));

        // Save response snapshot
        $intent->response_payload = $resp;
        $intent->save();

        // Example mapping (adjust once you see real OnePay response):
        $isPaid = in_array($status, ['success', 'paid', 'completed', 'captured'], true);

        if ($isPaid) {
            DB::transaction(function () use ($order, $intent, $inv) {
                // capture inventory + mark paid
                $inv->captureOrder($order);

                $intent->status = 'captured';
                $intent->save();

                $order->status = 'confirmed';
                $order->payment_status = 'paid';
                $order->save();
            });
        }

        return response()->json([
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
            'onepay' => $resp,
        ]);
    }
}