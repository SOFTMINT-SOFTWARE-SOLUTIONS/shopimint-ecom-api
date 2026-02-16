<?php

return [
    'base_url' => env('KOKO_BASE_URL', 'https://prodapi.paykoko.com'),
    'merchant_id' => env('KOKO_MERCHANT_ID'), // NOTE: docs say “Encrypted value needs to be passed”
    'api_key' => env('KOKO_API_KEY'),

    'plugin_name' => env('KOKO_PLUGIN_NAME', 'customapi'),
    'plugin_version' => env('KOKO_PLUGIN_VERSION', '1.0.1'),

    // Koko says credentials should be sent as authorization header (username + password).
    // Keep optional; enable if they gave you these.
    'auth_user' => env('KOKO_AUTH_USER'),
    'auth_pass' => env('KOKO_AUTH_PASS'),

    // Merchant private key used to SIGN requests (orderCreate / orderView)
    'merchant_private_key_path' => env('KOKO_MERCHANT_PRIVATE_KEY_PATH', storage_path('app/keys/koko_merchant_private.pem')),

    // KOKO public key used to VERIFY response signatures
    'koko_public_key_path' => env('KOKO_PUBLIC_KEY_PATH', storage_path('app/keys/koko_public.pem')),

    // Your endpoints (frontend return pages + backend webhook)
    'return_url' => env('KOKO_RETURN_URL'),     // e.g. https://www.siriwardanamobile.lk/payments/koko/return
    'cancel_url' => env('KOKO_CANCEL_URL'),     // e.g. https://www.siriwardanamobile.lk/payments/koko/cancel
    'response_url' => env('KOKO_RESPONSE_URL'), // e.g. https://api.siriwardanamobile.lk/api/webhooks/koko
];