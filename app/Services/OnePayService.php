<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OnePayService
{
    public function createCheckoutLink(array $payload): array
    {
        $baseUrl = rtrim(config('onepay.base_url'), '/');
        $url = $baseUrl . '/v3/checkout/link/';

        $res = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (!$res->successful()) {
            throw new \RuntimeException('OnePay error: ' . $res->body());
        }

        return $res->json();
    }

    public function getTransactionStatus(string $onepayTransactionId): array
    {
        $baseUrl = rtrim(config('onepay.base_url'), '/');
        $url = $baseUrl . '/v3/transaction/status/';

        $payload = [
            'app_id' => config('onepay.app_id'),
            'onepay_transaction_id' => $onepayTransactionId,
        ];

        $res = Http::timeout(30)->acceptJson()->asJson()->post($url, $payload);

        if (!$res->successful()) {
            throw new \RuntimeException('OnePay status error: ' . $res->body());
        }

        return $res->json();
    }

    public function makeHash(string $currency, string $amount2dp): string
    {
        $appId = config('onepay.app_id');
        $salt  = config('onepay.hash_salt');

        // docs: app_id + currency + amount + HASH_SALT then SHA-256  [oai_citation:1‡OnePay Developer](https://developer.onepay.lk/?utm_source=chatgpt.com)
        return hash('sha256', $appId . $currency . $amount2dp . $salt);
    }

    public function amount2dp($amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }
}