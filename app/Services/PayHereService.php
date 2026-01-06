<?php

namespace App\Services;

use App\Models\Order;

class PayHereService
{
    public function actionUrl(): string
    {
        $mode = config('payhere.mode', 'sandbox');
        return $mode === 'live'
            ? config('payhere.live_action_url')
            : config('payhere.sandbox_action_url');
    }

    public function amountFormatted(float $amount): string
    {
        // PayHere expects 2 decimals without commas in hash generation
        return number_format($amount, 2, '.', '');
    }

    public function generateHash(string $orderId, float $amount, string $currency): string
    {
        $merchantId = (string) config('payhere.merchant_id');
        $secret = (string) config('payhere.merchant_secret');

        $hashedSecret = strtoupper(md5($secret));

        // hash = strtoupper(md5(merchant_id + order_id + amount + currency + strtoupper(md5(merchant_secret))))
        $raw = $merchantId . $orderId . $this->amountFormatted($amount) . $currency . $hashedSecret;

        return strtoupper(md5($raw));
    }

    /**
     * Build POST fields for PayHere Checkout form.
     * Next.js should POST these to actionUrl().
     */
    public function buildCheckoutFields(Order $order): array
    {
        $merchantId = (string) config('payhere.merchant_id');
        $returnUrl  = (string) config('payhere.return_url');
        $cancelUrl  = (string) config('payhere.cancel_url');
        $notifyUrl  = (string) config('payhere.notify_url');

        $currency = $order->currency ?? 'LKR';
        $amount = (float) $order->grand_total;

        $hash = $this->generateHash($order->order_number, $amount, $currency);

        // Split guest name to first/last (PayHere requires both)
        $fullName = trim((string)($order->guest_name ?? 'Customer'));
        $parts = preg_split('/\s+/', $fullName);
        $firstName = $parts[0] ?? 'Customer';
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'NA';

        // Address fields: PayHere requires these too
        $shipping = is_array($order->shipping_address_json) ? $order->shipping_address_json : [];
        $address = trim((string)($shipping['address'] ?? $shipping['line1'] ?? 'N/A'));
        $city    = trim((string)($shipping['city'] ?? 'Colombo'));
        $country = trim((string)($shipping['country'] ?? 'Sri Lanka'));

        return [
            'merchant_id' => $merchantId,
            'return_url'  => $returnUrl,
            'cancel_url'  => $cancelUrl,
            'notify_url'  => $notifyUrl,

            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => (string)($order->guest_email ?? 'no-reply@siriwardanamobile.lk'),
            'phone'      => (string)($order->guest_phone ?? '0000000000'),
            'address'    => $address,
            'city'       => $city,
            'country'    => $country,

            'order_id'   => (string)$order->order_number,
            'items'      => 'Siriwardana Mobile Order ' . $order->order_number,
            'currency'   => $currency,
            'amount'     => $this->amountFormatted($amount),

            'hash'       => $hash,

            // Optional: You can pass custom params to identify in notify
            'custom_1'   => (string)$order->id,
            'custom_2'   => 'CARD_PAYHERE',
        ];
    }

    /**
     * Verify PayHere md5sig from notify_url.
     * md5sig = strtoupper(md5(merchant_id + order_id + payhere_amount + payhere_currency + status_code + strtoupper(md5(merchant_secret))))
     */
    public function verifyMd5Sig(array $payload): bool
    {
        $secret = (string) config('payhere.merchant_secret');
        $merchantId = (string)($payload['merchant_id'] ?? '');
        $orderId = (string)($payload['order_id'] ?? '');
        $amount = (string)($payload['payhere_amount'] ?? '');
        $currency = (string)($payload['payhere_currency'] ?? '');
        $status = (string)($payload['status_code'] ?? '');
        $md5sig = (string)($payload['md5sig'] ?? '');

        if ($merchantId === '' || $orderId === '' || $amount === '' || $currency === '' || $status === '' || $md5sig === '') {
            return false;
        }

        $hashedSecret = strtoupper(md5($secret));
        $local = strtoupper(md5($merchantId . $orderId . $amount . $currency . $status . $hashedSecret));

        return hash_equals($local, strtoupper($md5sig));
    }
}
