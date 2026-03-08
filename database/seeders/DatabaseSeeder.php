<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['gstin' => '29AABCZ5012F1ZG'],
            [
                'name' => 'Zysk Technologies Private Limited - 2025 - 2026',
                'state' => 'Karnataka',
                'gst_registration_type' => 'Regular',
                'financial_year' => '2025-2026',
                'currency' => 'INR',
            ],
        );

        if (app()->environment('local', 'testing')) {
            $user = User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@zysk.in',
            ]);

            $company->users()->syncWithoutDetaching([
                $user->id => ['role' => UserRole::Admin->value],
            ]);
        }
    }
}
