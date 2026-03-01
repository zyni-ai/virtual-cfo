<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        $bank = fake()->randomElement(['HDFC', 'ICICI', 'SBI', 'Axis', 'Kotak']);

        return [
            'company_id' => Company::factory(),
            'name' => $bank,
            'account_number' => fake()->numerify('################'),
            'ifsc_code' => strtoupper(fake()->lexify('????')).'0'.fake()->numerify('######'),
            'branch' => fake()->city(),
            'account_type' => AccountType::Current,
            'is_active' => true,
        ];
    }

    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Savings,
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::CreditCard,
            'ifsc_code' => null,
            'branch' => null,
        ]);
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
