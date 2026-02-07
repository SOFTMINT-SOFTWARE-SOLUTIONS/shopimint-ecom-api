<?php

return [
    'app_id' => env('ONEPAY_APP_ID'),
    'hash_salt' => env('ONEPAY_HASH_SALT'),
    'api_key' => env('ONEPAY_API_KEY'),
    'base_url' => env('ONEPAY_BASE_URL', 'https://api.onepay.lk'),
    'redirect_url' => env('ONEPAY_REDIRECT_URL'),
    'currency' => 'LKR',
];