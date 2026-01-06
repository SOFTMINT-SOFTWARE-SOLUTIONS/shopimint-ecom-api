<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Variant;
use App\Models\PaymentMethod;
use App\Models\PaymentIntent;
use App\Services\InventoryReservationService;

class SampleOrderSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // 1️⃣ Pick an existing variant (IMPORTANT)
            $variant = Variant::where('is_active', true)->first();

            if (!$variant) {
                throw new \RuntimeException('No active variants found. Create a product + variant first.');
            }

            // 2️⃣ Create customer (guest style)
            $customer = Customer::create([
                'first_name' => 'Test',
                'last_name'  => 'Customer',
                'phone'      => '0771234567',
                'email'      => 'test.customer@example.com',
                'marketing_opt_in' => false,
            ]);

            // 3️⃣ Create order
            $order = Order::create([
                'order_number' => 'TEST-' . strtoupper(Str::random(8)),
                'customer_id'  => $customer->id,

                'guest_name'  => 'Test Customer',
                'guest_email' => 'test.customer@example.com',
                'guest_phone' => '0771234567',

                'currency' => 'LKR',

                'subtotal' => $variant->price,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'grand_total' => $variant->price,

                'fulfillment_method' => 'delivery',

                'status' => 'confirmed',
                'payment_status' => 'unpaid',

                'shipping_address_json' => [
                    'address' => 'No 123, Sample Road',
                    'city' => 'Colombo',
                    'country' => 'Sri Lanka',
                ],
            ]);

            // 4️⃣ Order item
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'title' => $variant->product->title,
                'variant_title' => $variant->title,
                'sku' => $variant->sku,
                'quantity' => 1,
                'unit_price' => $variant->price,
                'line_total' => $variant->price,
            ]);

            // 5️⃣ Inventory reservation + capture
            $inv = app(InventoryReservationService::class);

            $inv->reserveForOrder(
                $order,
                $inv->mainLocationId(),
                [
                    [
                        'variant_id' => $variant->id,
                        'quantity' => 1,
                    ],
                ]
            );

            $inv->captureOrder($order);

            // 6️⃣ Create COD payment intent
            $method = PaymentMethod::where('code', 'COD')->first();

            if (!$method) {
                throw new \RuntimeException('Payment method COD not found. Seed payment methods first.');
            }

            PaymentIntent::create([
                'order_id' => $order->id,
                'payment_method_id' => $method->id,
                'gateway_id' => null,

                'amount' => $order->grand_total,
                'currency' => 'LKR',

                'status' => 'pending',
                'gateway_reference' => null,

                'request_payload' => [
                    'type' => 'COD',
                    'note' => 'Sample order for admin testing',
                ],
            ]);
        });
    }
}
