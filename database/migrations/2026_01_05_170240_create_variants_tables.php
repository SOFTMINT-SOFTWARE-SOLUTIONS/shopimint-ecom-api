<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();

            $table->string('title'); // "Black / 256GB"
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();

            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->char('currency', 3)->default('LKR');

            $table->boolean('is_active')->default(true)->index();

            // Optional "default" variant
            $table->boolean('is_default')->default(false)->index();

            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('variant_option_values', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('option_value_id');

            $table->primary(['variant_id', 'option_value_id']);

            $table->foreign('variant_id')->references('id')->on('variants')->cascadeOnDelete();
            $table->foreign('option_value_id')->references('id')->on('product_option_values')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('variants');
    }
};
