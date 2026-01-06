<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // featured image path (stored as string)
            $table->string('featured_image')->nullable()->after('slug');

            // product-level pricing (useful for simple products or display price)
            $table->decimal('compare_price', 12, 2)->nullable()->after('featured_image');
            $table->decimal('sell_price', 12, 2)->nullable()->after('compare_price');

            // quick availability toggle
            $table->boolean('in_stock')->default(true)->after('sell_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['featured_image', 'compare_price', 'sell_price', 'in_stock']);
        });
    }
};
