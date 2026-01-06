<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Link to users for logged-in customers (nullable for guests)
            $table->unsignedBigInteger('user_id')->nullable()->unique();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 30)->nullable()->index();

            $table->string('email')->nullable()->index(); // useful for guest orders too
            $table->boolean('marketing_opt_in')->default(false);

            $table->timestamps();

            // If you want FK now (only if users table exists)
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
