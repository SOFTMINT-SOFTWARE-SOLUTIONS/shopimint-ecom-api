<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->enum('product_type', ['phone', 'accessory', 'watch'])->index();

            $table->unsignedBigInteger('brand_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->index(); // recommended: level 3 leaf

            $table->string('title');
            $table->string('slug')->unique();

            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            $table->enum('status', ['draft', 'active', 'archived'])->default('draft')->index();
            $table->boolean('featured')->default(false)->index();

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
