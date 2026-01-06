<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;
use App\Models\PaymentGateway;

class PaymentSeed extends Seeder
{
    public function run(): void
    {
        PaymentGateway::updateOrCreate(['code' => 'payhere'], ['name' => 'PayHere', 'is_active' => 1]);
        PaymentGateway::updateOrCreate(['code' => 'onepay'],  ['name' => 'OnePay',  'is_active' => 1]);
        PaymentGateway::updateOrCreate(['code' => 'koko'],    ['name' => 'Koko',    'is_active' => 1]);
        PaymentGateway::updateOrCreate(['code' => 'payzee'],  ['name' => 'Payzee',  'is_active' => 1]);

        PaymentMethod::updateOrCreate(['code' => 'CARD_PAYHERE'], ['name' => 'Credit/Debit Card (PayHere)', 'type' => 'card', 'is_active' => 1]);
        PaymentMethod::updateOrCreate(['code' => 'CARD_ONEPAY'],  ['name' => 'Credit/Debit Card (OnePay)',  'type' => 'card', 'is_active' => 1]);
        PaymentMethod::updateOrCreate(['code' => 'KOKO_INSTALLMENT'],  ['name' => 'Koko Installments (3–6 months)', 'type' => 'installment', 'is_active' => 1]);
        PaymentMethod::updateOrCreate(['code' => 'PAYZEE_INSTALLMENT'],['name' => 'Payzee Installments (3–6 months)', 'type' => 'installment', 'is_active' => 1]);
        PaymentMethod::updateOrCreate(['code' => 'COD'],    ['name' => 'Cash on Delivery', 'type' => 'cod', 'is_active' => 1]);
        PaymentMethod::updateOrCreate(['code' => 'PICKUP'], ['name' => 'Pickup from Shop', 'type' => 'pickup', 'is_active' => 1]);
    }
}
