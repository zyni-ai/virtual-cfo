<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CreditCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCard>
 */
class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    public function definition(): array
    {
        $bank = fake()->randomElement(['HDFC', 'ICICI', 'SBI', 'Axis', 'Kotak']);

        return [
            'company_id' => Company::factory(),
            'name' => $bank.' Credit Card',
            'card_number' => fake()->numerify('################'),
            'is_active' => true,
        ];
    }

    public function withPassword(string $password = 'test1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'pdf_password' => $password,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
