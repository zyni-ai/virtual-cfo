<?php

namespace Database\Factories;

use App\Enums\ConnectorProvider;
use App\Models\Company;
use App\Models\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    protected $model = Connector::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'provider' => ConnectorProvider::Zoho,
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'settings' => ['organization_id' => fake()->numerify('##########')],
            'last_synced_at' => null,
            'is_active' => true,
        ];
    }

    public function zohoConnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => ConnectorProvider::Zoho,
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
            'last_synced_at' => now()->subHours(2),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subHour(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
