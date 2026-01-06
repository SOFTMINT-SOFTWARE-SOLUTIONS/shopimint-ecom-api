<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Main Shop, Warehouse
            $table->string('code', 50)->unique(); // MAIN_SHOP, WAREHOUSE
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_id')->unique();
            $table->timestamps();

            $table->foreign('variant_id')->references('id')->on('variants')->cascadeOnDelete();
        });

        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->unsignedBigInteger('location_id')->index();

            $table->integer('available')->default(0);
            $table->integer('reserved')->default(0);

            $table->timestamps();

            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();

            $table->unique(['inventory_item_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_levels');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('locations');
    }
};
