<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Str;

class CartService
{
    public function getOrCreateCart(?int $customerId, ?string $guestToken): Cart
    {
        // Logged-in customer cart
        if ($customerId) {
            return Cart::firstOrCreate(
                ['customer_id' => $customerId, 'status' => 'active'],
                ['currency' => 'LKR']
            );
        }

        // Guest cart
        $token = $guestToken ?: Str::random(40);

        // ✅ If there's already an ACTIVE cart for this token, reuse it
        $active = Cart::where('guest_token', $token)
            ->where('status', 'active')
            ->first();

        if ($active) {
            return $active;
        }

        /**
         * ✅ IMPORTANT:
         * If the same token exists in DB but NOT active (ex: converted),
         * we must generate a NEW token because guest_token is UNIQUE.
         */
        $tokenExists = Cart::where('guest_token', $token)->exists();
        if ($tokenExists) {
            $token = Str::random(40);

            // very rare safety: ensure newly generated token doesn't exist
            while (Cart::where('guest_token', $token)->exists()) {
                $token = Str::random(40);
            }
        }

        // ✅ Create a brand new ACTIVE cart with a safe token
        return Cart::create([
            'guest_token' => $token,
            'status' => 'active',
            'currency' => 'LKR',
        ]);
    }

    public function summary(Cart $cart, string $fulfillmentMethod, string $paymentMethodCode): array
    {
        $subtotal = 0;

        foreach ($cart->items as $item) {
            $subtotal += ((float) $item->unit_price * (int) $item->quantity);
        }

        // Shipping rules
        $shipping = ($fulfillmentMethod === 'pickup') ? 0 : 300;

        // Payment fee rules
        $paymentMethodCode = strtoupper($paymentMethodCode);

        $feeRate = 0;
        if (in_array($paymentMethodCode, ['PAYZEE', 'KOKO'], true)) $feeRate = 0.12;
        if ($paymentMethodCode === 'ONEPAY') $feeRate = 0.03;

        $paymentFee = round($subtotal * $feeRate, 2);

        $discount = 0;
        $tax = 0;

        $grandTotal = $subtotal + $shipping + $paymentFee - $discount + $tax;

        return [
            'currency' => $cart->currency ?? 'LKR',
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'payment_fee_total' => $paymentFee,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'grand_total' => $grandTotal,
            'payment_fee_rate' => $feeRate,
        ];
    }
}