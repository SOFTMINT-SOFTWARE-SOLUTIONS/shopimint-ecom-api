<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('name'); // Color, Storage, etc.
            $table->tinyInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('option_id')->index();
            $table->string('value'); // Black, 256GB
            $table->tinyInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('option_id')->references('id')->on('product_options')->cascadeOnDelete();
            $table->unique(['option_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
    }
};
