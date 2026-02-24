<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create default admin user if none exists
        if (User::count() === 0) {
            User::create([
                'name' => 'Admin',
                'email' => 'admin@zysk.in',
                'password' => bcrypt('password'),
            ]);
        }
    }
}
