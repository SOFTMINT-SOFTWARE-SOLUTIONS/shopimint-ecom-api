<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('guest_token', 64)->nullable()->unique();

            $table->char('currency', 3)->default('LKR');
            $table->enum('status', ['active', 'converted', 'abandoned'])->default('active')->index();

            $table->timestamps();

            // If you already have customers table later, you can add FK then
            // $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
