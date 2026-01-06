<?php

namespace App\Domains\Payments\Contracts;

use App\Models\Order;

interface PaymentProvider
{
    public function start(Order $order, array $payload): array; // returns redirect_url or payment_session
    public function verify(array $payload): array;              // returns status + transaction ref
}
