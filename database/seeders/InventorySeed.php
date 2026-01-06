<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class InventorySeed extends Seeder
{
    public function run(): void
    {
        Location::updateOrCreate(
            ['code' => 'MAIN_SHOP'],
            ['name' => 'Main Shop', 'address' => null, 'is_active' => 1]
        );

        Location::updateOrCreate(
            ['code' => 'WAREHOUSE'],
            ['name' => 'Warehouse', 'address' => null, 'is_active' => 1]
        );
    }
}
