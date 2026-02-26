<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['gstin' => config('company.gstin', '29AABCZ5012F1ZG')],
            [
                'name' => config('company.name', 'Zysk Technologies Private Limited - 2025 - 2026'),
                'state' => config('company.state', 'Karnataka'),
                'gst_registration_type' => config('company.gst_registration_type', 'Regular'),
                'financial_year' => config('company.financial_year', '2025-2026'),
                'currency' => config('company.currency', 'INR'),
            ],
        );

        (new AccountHeadSeeder)->run($company);

        if (app()->environment('local', 'testing')) {
            $user = User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@zysk.in',
            ]);

            $company->users()->syncWithoutDetaching($user);
        }
    }
}
