<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@zysk.in',
            ]);
        }
    }
}
