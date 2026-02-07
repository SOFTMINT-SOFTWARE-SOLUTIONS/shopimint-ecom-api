<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\InventoryReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\OrderPlacedMail;

class CheckoutController extends Controller
{
    public function checkout(
        Request $request,
        CartService $cartService,
        InventoryReservationService $inventoryReservationService
    ) {
        $data = $request->validate([
            'fulfillment_method'   => 'required|in:delivery,pickup',
            'payment_method_code'  => 'required|string|max:60', // ✅ IMPORTANT (COD, PICKUP, BANK, ONEPAY, KOKO, PAYZEE)

            'guest_name'  => 'required|string|max:190',
            'guest_email' => 'nullable|email|max:190',
            'guest_phone' => 'required|string|max:30',

            'shipping_address' => 'nullable|array',
            'billing_address'  => 'nullable|array',
            'notes'            => 'nullable|string|max:2000',
        ]);

        if ($data['fulfillment_method'] === 'delivery' && empty($data['shipping_address'])) {
            return response()->json(['message' => 'Shipping address is required for delivery'], 422);
        }

        // ✅ Always use same guest token (cart exists on server; products are NOT sent in request body)
        $guestToken = $request->header('X-Guest-Token') ?? $request->get('guest_token');

        $cart = $cartService->getOrCreateCart(null, $guestToken);
        $cart->load(['items.variant.product']);

        if ($cart->items->count() === 0) {
            return response()->json([
                'message' => 'Cart is empty',
                'guest_token' => $cart->guest_token,
            ], 422);
        }

        try {
            $order = DB::transaction(function () use (
                $cart,
                $data,
                $cartService,
                $inventoryReservationService
            ) {
                // 1) Create/find customer (guest checkout)
                $customer = Customer::firstOrCreate(
                    ['phone' => $data['guest_phone']],
                    [
                        'first_name'       => $data['guest_name'],
                        'email'            => $data['guest_email'] ?? null,
                        'last_name'        => null,
                        'marketing_opt_in' => false,
                        'user_id'          => null,
                    ]
                );

                if (!empty($data['guest_email']) && empty($customer->email)) {
                    $customer->email = $data['guest_email'];
                    $customer->save();
                }

                // 2) Refresh item prices from variants and compute subtotal
                $subtotal = 0;

                foreach ($cart->items as $item) {
                    $variant = Variant::lockForUpdate()->find($item->variant_id);

                    if (!$variant || !$variant->is_active) {
                        throw new \RuntimeException('One of the items is not available.');
                    }

                    $price = (float) $variant->price;

                    $item->unit_price = $price;
                    $item->currency   = $variant->currency ?? ($cart->currency ?? 'LKR');
                    $item->save();

                    $subtotal += ($price * (int) $item->quantity);
                }

                // 3) Calculate totals using your rules (shipping + payment fee)
                // NOTE: This requires CartService::summary($cart, $fulfillment, $paymentMethodCode)
                $fulfillment       = $data['fulfillment_method'];
                $paymentMethodCode = $data['payment_method_code'];

                $summary = $cartService->summary($cart, $fulfillment, $paymentMethodCode);

                // Keep these for clarity
                $discount = 0;
                $tax = 0;

                $orderNumber = 'SM' . date('ymd') . strtoupper(Str::random(6));

                // 4) Create order
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'customer_id'  => $customer->id,

                    'guest_name'  => $data['guest_name'],
                    'guest_email' => $data['guest_email'] ?? null,
                    'guest_phone' => $data['guest_phone'],

                    'currency' => $cart->currency ?? 'LKR',

                    'subtotal'       => $subtotal,
                    'discount_total' => $discount,
                    'shipping_total' => (float) ($summary['shipping_total'] ?? 0),
                    'tax_total'      => (float) ($summary['tax_total'] ?? $tax),
                    'grand_total'    => (float) ($summary['grand_total'] ?? ($subtotal - $discount)),

                    'fulfillment_method' => $fulfillment,
                    'status'         => 'pending',
                    'payment_status' => 'unpaid',

                    'shipping_address_json' => $fulfillment === 'delivery'
                        ? ($data['shipping_address'] ?? null)
                        : null,

                    'billing_address_json' => $data['billing_address'] ?? null,
                    'notes'                => $data['notes'] ?? null,
                ]);

                // 5) Create order items
                foreach ($cart->items as $item) {
                    $variant = $item->variant;  // loaded by $cart->load(...)
                    $product = $variant->product;

                    $qty       = (int) $item->quantity;
                    $unitPrice = (float) $item->unit_price;

                    $order->items()->create([
                        'product_id'     => $product->id,
                        'variant_id'     => $variant->id,
                        'title'          => $product->title,
                        'variant_title'  => $variant->title,
                        'sku'            => $variant->sku,
                        'quantity'       => $qty,
                        'unit_price'     => $unitPrice,
                        'line_total'     => $unitPrice * $qty,
                    ]);
                }

                // 6) Reserve inventory for this order (Main Shop)
                $reserveItems = $order->items->map(fn ($i) => [
                    'variant_id' => $i->variant_id,
                    'quantity'   => (int) $i->quantity,
                ])->values()->all();

                $inventoryReservationService->reserveForOrder(
                    $order,
                    $inventoryReservationService->mainLocationId(),
                    $reserveItems
                );

                // 7) Mark cart converted
                $cart->status = 'converted';
                $cart->save();

                return $order;
            });

            if (!empty($order->guest_email)) {
                Mail::to($order->guest_email)->queue(new OrderPlacedMail($order));
            }

            return response()->json([
                'status' => true,
                'message' => 'Order created (stock reserved)',
                'order' => $order->load('items'),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}