<?php
namespace App\Services;
use App\Models\Cart;

class CartService
{
    public function getOrCreateCart(?int $customerId, ?string $guestToken): Cart
    {
        if ($customerId) {
            return Cart::firstOrCreate(
                ['customer_id' => $customerId, 'status' => 'active'],
                ['currency' => 'LKR']
            );
        }

        $token = $guestToken ?: \Illuminate\Support\Str::random(40);

        return Cart::firstOrCreate(
            ['guest_token' => $token, 'status' => 'active'],
            ['currency' => 'LKR']
        );
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
