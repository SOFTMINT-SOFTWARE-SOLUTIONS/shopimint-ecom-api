<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If status is VARCHAR, ignore. If ENUM, convert to VARCHAR to avoid enum headaches.
        // This is the safest production approach.
        DB::statement("ALTER TABLE `orders` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // no-op
    }
};
