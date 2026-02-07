<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Services\InventoryReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnePayWebhookController extends Controller
{
    /**
     * OnePay Webhook (server-to-server)
     *
     * Expected payload example:
     * {
     *   "transaction_id": "WQBV118E584C83CBA50C6",
     *   "status": 1,
     *   "status_message": "SUCCESS",
     *   "additional_data": "..." // optional
     * }
     *
     * Notes:
     * - We match PaymentIntent by gateway_reference == transaction_id.
     * - If you also send order_number in additional_data when creating checkout link,
     *   you can add a fallback to find order by that too.
     */
    public function handle(Request $request, InventoryReservationService $inv)
    {
        $data = $request->validate([
            'transaction_id'  => 'required|string|max:120',
            'status'          => 'required',
            'status_message'  => 'nullable|string|max:190',
            'additional_data' => 'nullable|string',
        ]);

        $transactionId = $data['transaction_id'];
        $status = (int) $data['status'];
        $statusMessage = strtoupper(trim((string)($data['status_message'] ?? '')));
        $additionalData = $data['additional_data'] ?? null;

        // ✅ Find intent by OnePay transaction id
        $intent = PaymentIntent::where('gateway_reference', $transactionId)->latest()->first();

        if (!$intent) {
            Log::warning('OnePay webhook: PaymentIntent not found', [
                'transaction_id' => $transactionId,
                'payload' => $data,
            ]);

            // Return 200 so OnePay doesn't retry forever (your choice).
            return response()->json([
                'ok' => true,
                'message' => 'Intent not found (ignored)',
            ]);
        }

        $order = Order::with('items')->find($intent->order_id);

        if (!$order) {
            Log::warning('OnePay webhook: Order not found', [
                'transaction_id' => $transactionId,
                'intent_id' => $intent->id,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Order not found (ignored)',
            ]);
        }

        // Store payload snapshot for audit/debug
        $intent->response_payload = [
            'webhook' => $data,
            'received_at' => now()->toISOString(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        $intent->save();

        // ✅ Idempotent: if already paid/captured, do nothing
        if ($order->payment_status === 'paid' || $intent->status === 'captured') {
            return response()->json([
                'ok' => true,
                'message' => 'Already processed',
            ]);
        }

        $isSuccess = ($status === 1) && ($statusMessage === 'SUCCESS');

        if ($isSuccess) {
            DB::transaction(function () use ($order, $intent, $inv) {
                // Capture inventory only on success
                $inv->captureOrder($order);

                $intent->status = 'captured';
                $intent->save();

                $order->status = 'confirmed';
                $order->payment_status = 'paid';
                $order->save();
            });

            return response()->json([
                'ok' => true,
                'message' => 'Payment captured and order confirmed',
                'order_number' => $order->order_number,
            ]);
        }

        // If not success, keep pending OR mark failed based on your business rule
        // Shopify-style: keep pending unless explicit "FAILED"
        // You can adjust mapping if OnePay provides more statuses.
        $intent->status = 'failed';
        $intent->save();

        // Optionally release reservation on failure:
        // DB::transaction(function () use ($order, $inv) {
        //     $inv->releaseOrder($order);
        //     $order->status = 'cancelled';
        //     $order->payment_status = 'unpaid';
        //     $order->save();
        // });

        return response()->json([
            'ok' => true,
            'message' => 'Payment not successful (intent marked failed)',
            'order_number' => $order->order_number,
        ]);
    }
}