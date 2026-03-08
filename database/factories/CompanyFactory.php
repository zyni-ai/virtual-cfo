<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $stateCode = fake()->numberBetween(1, 38);

        return [
            'name' => fake()->company().' - '.fake()->year(),
            'gstin' => str_pad($stateCode, 2, '0', STR_PAD_LEFT).strtoupper(fake()->bothify('?????####?')).'1Z'.strtoupper(fake()->randomLetter()),
            'state' => fake()->randomElement(['Karnataka', 'Maharashtra', 'Tamil Nadu', 'Delhi', 'Gujarat']),
            'gst_registration_type' => 'Regular',
            'financial_year' => '2025-2026',
            'currency' => 'INR',
            'review_confidence_threshold' => 0.80,
            'fy_start_month' => 4,
        ];
    }

    public function withIdentity(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_holder_name' => fake()->name(),
            'date_of_birth' => fake()->date('d/m/Y', '2000-01-01'),
            'pan_number' => strtoupper(fake()->bothify('?????####?')),
            'mobile_number' => fake()->numerify('98########'),
        ]);
    }

    public function zysk(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Zysk Technologies Private Limited - 2025 - 2026',
            'gstin' => '29AABCZ5012F1ZG',
            'state' => 'Karnataka',
            'gst_registration_type' => 'Regular',
            'financial_year' => '2025-2026',
            'currency' => 'INR',
        ]);
    }
}
