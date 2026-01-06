<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::updateOrCreate(
            ['email' => 'admin@siriwardanamobile.lk'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@123'),
                'status' => 'active',
            ]
        );
    }
}
