<?php

return [
    'mode' => env('PAYHERE_MODE', 'sandbox'), // sandbox|live
    'merchant_id' => env('PAYHERE_MERCHANT_ID'),
    'merchant_secret' => env('PAYHERE_MERCHANT_SECRET'),

    'return_url' => env('PAYHERE_RETURN_URL'),
    'cancel_url' => env('PAYHERE_CANCEL_URL'),
    'notify_url' => env('PAYHERE_NOTIFY_URL'),

    'sandbox_action_url' => 'https://sandbox.payhere.lk/pay/checkout',
    'live_action_url' => 'https://www.payhere.lk/pay/checkout',
];
