<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PaymentMethod;
use App\Services\InventoryReservationService;
use App\Services\PayHereService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayHereWebhookController extends Controller
{
    public function notify(
        Request $request,
        PayHereService $payHere,
        InventoryReservationService $inv
    ) {
        // PayHere sends x-www-form-urlencoded (not JSON) :contentReference[oaicite:2]{index=2}
        $payload = $request->all();

        // Basic verify
        if (!$payHere->verifyMd5Sig($payload)) {
            Log::warning('PayHere notify invalid md5sig', $payload);
            return response('INVALID', 400);
        }

        $merchantId = (string)($payload['merchant_id'] ?? '');
        if ($merchantId !== (string)config('payhere.merchant_id')) {
            Log::warning('PayHere merchant mismatch', $payload);
            return response('INVALID', 400);
        }

        $orderNumber = (string)($payload['order_id'] ?? '');
        $statusCode = (int)($payload['status_code'] ?? 0);

        $order = Order::with('items')->where('order_number', $orderNumber)->first();
        if (!$order) {
            Log::warning('PayHere order not found', $payload);
            return response('OK', 200); // don't retry forever
        }

        $method = PaymentMethod::where('code', 'CARD_PAYHERE')->first();
        if (!$method) {
            Log::error('PaymentMethod CARD_PAYHERE missing');
            return response('OK', 200);
        }

        // Get latest pending intent (or any intent)
        $intent = PaymentIntent::where('order_id', $order->id)
            ->where('payment_method_id', $method->id)
            ->latest()
            ->first();

        if (!$intent) {
            // Create one for safety (rare case)
            $intent = PaymentIntent::create([
                'order_id' => $order->id,
                'payment_method_id' => $method->id,
                'gateway_id' => null,
                'amount' => $order->grand_total,
                'currency' => $order->currency ?? 'LKR',
                'status' => 'pending',
                'request_payload' => null,
                'response_payload' => null,
                'webhook_payload' => null,
            ]);
        }

        // Idempotent: if already paid/captured, accept webhook
        if ($order->payment_status === 'paid' || $intent->status === 'captured') {
            return response('OK', 200);
        }

        // Optional: Amount/currency check (recommended)
        $payhereAmount = (string)($payload['payhere_amount'] ?? '');
        $payhereCurrency = (string)($payload['payhere_currency'] ?? '');
        if ($payhereCurrency !== ($order->currency ?? 'LKR')) {
            Log::warning('PayHere currency mismatch', $payload);
            // You may choose to reject or just log.
        }

        DB::transaction(function () use ($payload, $statusCode, $order, $intent, $inv) {

            // Save webhook payload always
            $intent->webhook_payload = $payload;
            $intent->gateway_reference = (string)($payload['payment_id'] ?? null);

            if ($statusCode === 2) {
                // ✅ SUCCESS: capture inventory + mark order paid/confirmed
                $inv->captureOrder($order);

                $intent->status = 'captured';
                $intent->response_payload = [
                    'status' => 'success',
                    'status_code' => 2,
                    'method' => $payload['method'] ?? null,
                ];
                $intent->save();

                $order->payment_status = 'paid';
                $order->status = 'confirmed';
                $order->save();

            } elseif ($statusCode === 0) {
                // ⏳ pending: keep as pending
                $intent->status = 'pending';
                $intent->response_payload = [
                    'status' => 'pending',
                    'status_code' => 0,
                ];
                $intent->save();

                // keep order pending/unpaid

            } else {
                // ❌ failed/canceled/chargedback: release reserved stock
                $inv->releaseOrder($order);

                $intent->status = 'failed';
                $intent->response_payload = [
                    'status' => 'failed',
                    'status_code' => $statusCode,
                    'message' => $payload['status_message'] ?? null,
                ];
                $intent->save();

                $order->payment_status = 'failed';
                $order->status = 'cancelled'; // or keep pending and allow retry
                $order->save();
            }
        });

        return response('OK', 200);
    }
}
