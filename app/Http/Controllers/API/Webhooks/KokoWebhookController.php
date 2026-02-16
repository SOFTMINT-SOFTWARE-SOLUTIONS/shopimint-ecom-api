<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Services\InventoryReservationService;
use App\Services\KokoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KokoWebhookController extends Controller
{
    public function handle(Request $request, InventoryReservationService $inv, KokoService $koko)
    {
        // KOKO sends application/x-www-form-urlencoded
        $data = $request->validate([
            'orderId' => 'required|string|max:120',
            'trnId'   => 'required|string|max:190',
            'status'  => 'required|string|max:50',
            'desc'    => 'nullable|string|max:255',
            'signature' => 'required|string',
        ]);

        $orderNumber = $request->query('order'); // we appended ?order=ORDER_NUMBER
        $orderId = $data['orderId'];
        $trnId = $data['trnId'];
        $status = strtoupper(trim($data['status']));
        $signature = $data['signature'];

        $order = $orderNumber
            ? Order::with('items')->where('order_number', $orderNumber)->first()
            : null;

        // fallback: find by intent gateway_reference (we stored KOKO-{intent_id} in gateway_reference)
        $intent = PaymentIntent::where('gateway_reference', $orderId)->latest()->first();

        if (!$order && $intent) {
            $order = Order::with('items')->find($intent->order_id);
        }

        if (!$order || !$intent) {
            Log::warning('KOKO webhook: order/intent not found', [
                'order_number' => $orderNumber,
                'orderId' => $orderId,
                'payload' => $data,
            ]);
            return response()->json(['ok' => true, 'message' => 'Not found (ignored)']);
        }

        // Verify signature using KOKO public key (orderId + trnId + status)
        $sigOk = $koko->verifyResponseSignature($orderId, $trnId, $status, $signature);

        // Store payload snapshot
        $intent->response_payload = [
            'webhook' => $data,
            'sig_ok' => $sigOk,
            'received_at' => now()->toISOString(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        $intent->save();

        if (!$sigOk) {
            Log::warning('KOKO webhook: signature invalid', [
                'orderId' => $orderId,
                'trnId' => $trnId,
                'status' => $status,
            ]);
            return response()->json(['ok' => true, 'message' => 'Invalid signature (ignored)']);
        }

        // Idempotent
        if ($order->payment_status === 'paid' || $intent->status === 'captured') {
            return response()->json(['ok' => true, 'message' => 'Already processed']);
        }

        if ($status === 'SUCCESS') {
            DB::transaction(function () use ($order, $intent, $inv, $trnId) {
                $inv->captureOrder($order);

                $intent->status = 'captured';
                // save KOKO transaction id too (optional, but useful)
                $intent->request_payload = array_merge((array)$intent->request_payload, [
                    'koko_trnId' => $trnId,
                ]);
                $intent->save();

                $order->status = 'confirmed';
                $order->payment_status = 'paid';
                $order->save();
            });

            return response()->json(['ok' => true, 'message' => 'Captured', 'order_number' => $order->order_number]);
        }

        // If failed/cancelled
        $intent->status = 'failed';
        $intent->save();

        return response()->json(['ok' => true, 'message' => 'Not successful', 'order_number' => $order->order_number]);
    }
}