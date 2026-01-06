<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // CARD_PAYHERE, CARD_ONEPAY, KOKO, PAYZEE, COD, PICKUP
            $table->string('name');
            $table->enum('type', ['card','installment','cod','pickup'])->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // payhere, onepay, koko, payzee
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();

            $table->unsignedBigInteger('payment_method_id')->index();
            $table->unsignedBigInteger('gateway_id')->nullable()->index();

            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('LKR');

            $table->enum('status', [
                'created','pending','authorized','captured','failed','cancelled','refunded'
            ])->default('created')->index();

            $table->string('gateway_reference')->nullable()->index(); // transaction id
            $table->string('redirect_url')->nullable(); // if gateway needs redirect

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('webhook_payload')->nullable();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->cascadeOnDelete();
            $table->foreign('gateway_id')->references('id')->on('payment_gateways')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('payment_methods');
    }
};
