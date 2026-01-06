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

        return Cart::firstOrCreate(
            ['guest_token' => $token, 'status' => 'active'],
            ['currency' => 'LKR']
        );
    }
}
