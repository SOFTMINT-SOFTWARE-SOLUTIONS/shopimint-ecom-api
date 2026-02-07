<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\PaymentMethod;
use App\Services\InventoryReservationService;
use App\Services\PayHereService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentStartController extends Controller
{
    public function start(
        Request $request,
        InventoryReservationService $inv,
        PayHereService $payHere
    ) {
        $data = $request->validate([
            'order_number' => 'required|string|max:50',
            'payment_method_code' => 'required|string|max:60',
        ]);

        $order = Order::with('items')->where('order_number', $data['order_number'])->firstOrFail();

        // Basic protection
        if (in_array($order->status, ['cancelled', 'delivered', 'refunded'], true)) {
            return response()->json(['message' => 'Order cannot accept payment in this status.'], 422);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Order already paid.'], 422);
        }

        if ($order->items->count() === 0) {
            return response()->json(['message' => 'Order has no items.'], 422);
        }

        $method = PaymentMethod::where('code', $data['payment_method_code'])->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | 1) CASH ON DELIVERY / PICKUP
        |--------------------------------------------------------------------------
        | - Capture inventory immediately
        | - Payment collected later
        */
        if (in_array($method->code, ['COD', 'PICKUP'], true)) {

            $result = DB::transaction(function () use ($order, $method, $inv) {

                // Idempotent check
                $existingIntent = PaymentIntent::where('order_id', $order->id)
                    ->where('payment_method_id', $method->id)
                    ->whereIn('status', ['created', 'pending', 'authorized', 'captured'])
                    ->latest()
                    ->first();

                if ($existingIntent && $order->status === 'confirmed') {
                    return [
                        'intent' => $existingIntent,
                        'order' => $order->fresh()->load('items'),
                        'idempotent' => true,
                    ];
                }

                // Ensure reservation exists, then capture immediately
                $reserveItems = $order->items->map(fn ($i) => [
                    'variant_id' => $i->variant_id,
                    'quantity' => (int) $i->quantity,
                ])->values()->all();

                $inv->reserveForOrder($order, $inv->mainLocationId(), $reserveItems);
                $inv->captureOrder($order);

                $intent = PaymentIntent::create([
                    'order_id' => $order->id,
                    'payment_method_id' => $method->id,
                    'gateway_id' => null,

                    'amount' => $order->grand_total,
                    'currency' => $order->currency ?? 'LKR',

                    'status' => 'pending',

                    'gateway_reference' => null,
                    'redirect_url' => null,

                    'request_payload' => [
                        'type' => $method->code,
                        'note' => 'COD / PICKUP. Inventory captured immediately.',
                    ],
                ]);

                $order->status = 'confirmed';
                $order->payment_status = 'unpaid';
                $order->save();

                return [
                    'intent' => $intent,
                    'order' => $order->fresh()->load('items'),
                    'idempotent' => false,
                ];
            });

            return response()->json([
                'message' => $method->code === 'COD'
                    ? 'Cash on Delivery selected.'
                    : 'Pickup selected.',
                'order' => $result['order'],
                'payment_intent' => $result['intent'],
                'idempotent' => $result['idempotent'],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2) PAYHERE (CARD)
        |--------------------------------------------------------------------------
        | - Keep order pending
        | - Capture inventory ONLY on webhook success
        */
        if ($method->code === 'CARD_PAYHERE') {

            $result = DB::transaction(function () use ($order, $method, $payHere) {

                // Idempotent: reuse existing pending intent
                $intent = PaymentIntent::where('order_id', $order->id)
                    ->where('payment_method_id', $method->id)
                    ->whereIn('status', ['created', 'pending'])
                    ->latest()
                    ->first();

                if (!$intent) {
                    $intent = PaymentIntent::create([
                        'order_id' => $order->id,
                        'payment_method_id' => $method->id,
                        'gateway_id' => null,

                        'amount' => $order->grand_total,
                        'currency' => $order->currency ?? 'LKR',

                        'status' => 'pending',
                        'request_payload' => [
                            'provider' => 'payhere',
                        ],
                    ]);
                }

                // Build PayHere checkout payload
                $fields = $payHere->buildCheckoutFields($order);

                $intent->request_payload = array_merge(
                    (array) ($intent->request_payload ?? []),
                    ['payhere_fields' => $fields]
                );
                $intent->save();

                // Order stays pending until webhook
                $order->status = 'pending';
                $order->payment_status = 'unpaid';
                $order->save();

                return [$intent, $fields];
            });

            [$intent, $fields] = $result;

            return response()->json([
                'message' => 'Redirect to PayHere',
                'order' => $order->fresh(),
                'payment_intent' => $intent,
                'gateway' => [
                    'provider' => 'payhere',
                    'action_url' => $payHere->actionUrl(),
                    'method' => 'POST',
                    'fields' => $fields,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3) INSTALLMENTS & OTHER GATEWAYS (PLACEHOLDERS)
        |--------------------------------------------------------------------------
        | Implement later using same pattern:
        | - Create intent (pending)
        | - Redirect or SDK start
        | - Capture inventory ONLY on success webhook
        */

        if (in_array($method->code, [
            'KOKO',
            'PAYZEE',
            'MINTPAY',
            'ONEPAY',
        ], true)) {
            return response()->json([
                'message' => "{$method->code} integration pending.",
                'todo' => [
                    'Create gateway service',
                    'Start payment',
                    'Webhook handler',
                    'Capture/release inventory',
                ],
            ], 501);
        }

        return response()->json(['message' => 'Unsupported payment method'], 422);
    }
}
