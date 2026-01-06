<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InventoryReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function cancel(Request $request, string $orderNumber, InventoryReservationService $inv)
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        // Simple rule: allow cancel only if not delivered
        if (in_array($order->status, ['delivered', 'refunded'], true)) {
            return response()->json(['message' => 'Order cannot be cancelled'], 422);
        }

        DB::transaction(function () use ($order, $inv) {
            // release reserved stock if still reserved
            $inv->releaseOrder($order);

            $order->status = 'cancelled';
            $order->payment_status = $order->payment_status === 'paid' ? 'refunded' : $order->payment_status;
            $order->save();
        });

        return response()->json(['message' => 'Order cancelled and stock released']);
    }
}
