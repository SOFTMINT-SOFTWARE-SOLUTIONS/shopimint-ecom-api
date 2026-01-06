<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('variant_id')->index();
            $table->unsignedBigInteger('location_id')->index();

            $table->integer('quantity');

            $table->enum('status', ['reserved', 'captured', 'released'])->default('reserved')->index();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('variants')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();

            // One reservation line per order+variant+location
            $table->unique(['order_id', 'variant_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
