<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('cart_id')->index();
            $table->unsignedBigInteger('variant_id')->index();

            $table->integer('quantity')->default(1);

            // Snapshot pricing (so cart doesn’t break if price changes)
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->char('currency', 3)->default('LKR');

            $table->timestamps();

            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('variants')->cascadeOnDelete();

            $table->unique(['cart_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
