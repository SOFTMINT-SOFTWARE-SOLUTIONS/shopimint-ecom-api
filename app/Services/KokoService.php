<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KokoService
{
    public function orderCreateUrl(): string
    {
        return rtrim(config('koko.base_url'), '/') . '/api/merchants/orderCreate';
    }

    public function orderViewUrl(): string
    {
        return rtrim(config('koko.base_url'), '/') . '/api/merchants/orderView';
    }

    /**
     * dataString creation order (NOT alphabetical) per docs.
     * mId + amount + currency + pluginName + pluginVersion + returnUrl + cancelUrl +
     * orderId + reference + firstName + lastName + email + description + apiKey + responseUrl
     */
    public function buildOrderCreateDataString(array $p): string
    {
        return (string)(
            $p['_mId']
            . $p['_amount']
            . $p['_currency']
            . $p['_pluginName']
            . $p['_pluginVersion']
            . $p['_returnUrl']
            . $p['_cancelUrl']
            . $p['_orderId']
            . $p['_reference']
            . $p['_firstName']
            . $p['_lastName']
            . $p['_email']
            . $p['_description']
            . $p['api_key']
            . $p['_responseUrl']
        );
    }

    public function sign(string $dataString): string
    {
        $privateKeyPem = file_get_contents(config('koko.merchant_private_key_path'));
        if (!$privateKeyPem) {
            throw new \RuntimeException('KOKO merchant private key not found/readable');
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new \RuntimeException('KOKO merchant private key invalid');
        }

        $signature = '';
        $ok = openssl_sign($dataString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        if (!$ok) {
            throw new \RuntimeException('KOKO signing failed');
        }

        return base64_encode($signature);
    }

    /**
     * Verify KOKO webhook/orderView signature using KOKO public key.
     * Docs show string-to-encrypt format: orderId + trnId + status
     */
    public function verifyResponseSignature(string $orderId, string $trnId, string $status, string $signatureB64): bool
    {
        $pubPem = file_get_contents(config('koko.koko_public_key_path'));
        if (!$pubPem) return false;

        $pubKey = openssl_pkey_get_public($pubPem);
        if (!$pubKey) return false;

        $dataString = $orderId . $trnId . strtoupper($status);
        $sig = base64_decode($signatureB64, true);
        if ($sig === false) return false;

        $ok = openssl_verify($dataString, $sig, $pubKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($pubKey);

        return $ok === 1;
    }

    public function httpClient()
    {
        $http = Http::timeout(30)->asForm();

        // Optional Basic auth if KOKO provided username/password (docs mention auth header username+password)
        if (config('koko.auth_user') && config('koko.auth_pass')) {
            $http = $http->withBasicAuth(config('koko.auth_user'), config('koko.auth_pass'));
        }

        return $http;
    }

    /**
     * Merchant Order View API signature:
     * MerchantID + PluginName + PluginVersion + OrderID + APIKey
     */
    public function buildOrderViewSignature(string $orderId): string
    {
        $dataString =
            config('koko.merchant_id')
            . config('koko.plugin_name')
            . config('koko.plugin_version')
            . $orderId
            . config('koko.api_key');

        return $this->sign($dataString);
    }

    public function orderView(string $orderId): array
    {
        $payload = [
            '_mId' => config('koko.merchant_id'),
            '_pluginName' => config('koko.plugin_name'),
            '_pluginVersion' => config('koko.plugin_version'),
            'api_key' => config('koko.api_key'),
            '_orderId' => $orderId,
            'signature' => $this->buildOrderViewSignature($orderId),
        ];

        $res = $this->httpClient()->post($this->orderViewUrl(), $payload);

        if (!$res->successful()) {
            throw new \RuntimeException('KOKO orderView error: ' . $res->body());
        }

        return $res->json() ?? $res->collect()->toArray();
    }
}