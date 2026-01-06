<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number', 30)->unique();

            $table->unsignedBigInteger('customer_id')->nullable()->index();

            // Guest details
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable()->index();
            $table->string('guest_phone')->nullable();

            $table->char('currency', 3)->default('LKR');

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            $table->enum('fulfillment_method', ['delivery', 'pickup'])->default('delivery')->index();
            $table->enum('status', ['pending', 'confirmed', 'processing', 'delivered', 'cancelled', 'refunded'])
                ->default('pending')->index();

            $table->enum('payment_status', ['unpaid', 'authorized', 'paid', 'partial_refund', 'refunded', 'failed'])
                ->default('unpaid')->index();

            // Snapshot addresses
            $table->json('shipping_address_json')->nullable();
            $table->json('billing_address_json')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
