<?php

return [
    'app_id' => env('ONEPAY_APP_ID'),
    'hash_salt' => env('ONEPAY_HASH_SALT'),
    'base_url' => env('ONEPAY_BASE_URL', 'https://api.onepay.lk'),
    'redirect_url' => env('ONEPAY_REDIRECT_URL'),
    'currency' => 'LKR',
];