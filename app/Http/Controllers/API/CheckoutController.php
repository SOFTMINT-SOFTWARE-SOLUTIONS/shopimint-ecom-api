<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Variant;
use App\Services\CartService;
use App\Services\InventoryReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;

class CheckoutController extends Controller
{
    public function checkout(
        Request $request,
        CartService $cartService,
        InventoryReservationService $inventoryReservationService
    ) {
        $data = $request->validate([
            'fulfillment_method' => 'required|in:delivery,pickup',

            'guest_name'  => 'required|string|max:190',
            'guest_email' => 'nullable|email|max:190',
            'guest_phone' => 'required|string|max:30',

            'shipping_address' => 'nullable|array',
            'billing_address'  => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($data['fulfillment_method'] === 'delivery' && empty($data['shipping_address'])) {
            return response()->json(['message' => 'Shipping address is required for delivery'], 422);
        }

        $guestToken = $request->header('X-Guest-Token') ?? $request->get('guest_token');
        $cart = $cartService->getOrCreateCart(null, $guestToken);
        $cart->load(['items.variant.product']);

        if ($cart->items->count() === 0) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        try {
            $order = DB::transaction(function () use ($cart, $data, $inventoryReservationService) {

                // Create/find customer
                $customer = Customer::firstOrCreate(
                    ['phone' => $data['guest_phone']],
                    [
                        'first_name' => $data['guest_name'],
                        'email' => $data['guest_email'] ?? null,
                        'last_name' => null,
                        'marketing_opt_in' => false,
                        'user_id' => null,
                    ]
                );

                if (!empty($data['guest_email']) && empty($customer->email)) {
                    $customer->email = $data['guest_email'];
                    $customer->save();
                }

                // Validate items + refresh pricing snapshot
                $subtotal = 0;

                foreach ($cart->items as $item) {
                    $variant = Variant::lockForUpdate()->find($item->variant_id);

                    if (!$variant || !$variant->is_active) {
                        throw new \RuntimeException('One of the items is not available.');
                    }

                    // IMPORTANT: Do NOT check variant.stock_quantity anymore in reservation-based flow.
                    // Stock check will happen via inventory_levels in reserve step.
                    $price = (float) $variant->price;

                    $item->unit_price = $price;
                    $item->currency = $variant->currency ?? ($cart->currency ?? 'LKR');
                    $item->save();

                    $subtotal += ($price * (int)$item->quantity);
                }

                $shipping = 0;
                $discount = 0;
                $tax = 0;

                $orderNumber = 'SM' . date('ymd') . strtoupper(Str::random(6));

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'customer_id' => $customer->id,

                    'guest_name' => $data['guest_name'],
                    'guest_email' => $data['guest_email'] ?? null,
                    'guest_phone' => $data['guest_phone'],

                    'currency' => $cart->currency ?? 'LKR',

                    'subtotal' => $subtotal,
                    'discount_total' => $discount,
                    'shipping_total' => $shipping,
                    'tax_total' => $tax,
                    'grand_total' => $subtotal - $discount + $shipping + $tax,

                    'fulfillment_method' => $data['fulfillment_method'],
                    'status' => 'pending',
                    'payment_status' => 'unpaid',

                    'shipping_address_json' => $data['fulfillment_method'] === 'delivery'
                        ? ($data['shipping_address'] ?? null)
                        : null,
                    'billing_address_json' => $data['billing_address'] ?? null,

                    'notes' => $data['notes'] ?? null,
                ]);

                // Create order items
                foreach ($cart->items as $item) {
                    $variant = $item->variant;
                    $product = $variant->product;

                    $qty = (int) $item->quantity;
                    $unitPrice = (float) $item->unit_price;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'title' => $product->title,
                        'variant_title' => $variant->title,
                        'sku' => $variant->sku,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $unitPrice * $qty,
                    ]);
                }

                // Reserve inventory for this order (Main Shop)
                $reserveItems = $order->items->map(fn ($i) => [
                    'variant_id' => $i->variant_id,
                    'quantity' => $i->quantity,
                ])->values()->all();

                $inventoryReservationService->reserveForOrder(
                    $order,
                    $inventoryReservationService->mainLocationId(),
                    $reserveItems
                );

                // Mark cart converted
                $cart->status = 'converted';
                $cart->save();

                return $order;
            });

            if (!empty($order->guest_email)) {
                Mail::to($order->guest_email)->queue(new OrderPlacedMail($order));
            }

            return response()->json([
                'message' => 'Order created (stock reserved)',
                'order' => $order->load('items'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
