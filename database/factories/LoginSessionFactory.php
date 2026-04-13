<?php

namespace Database\Factories;

use App\Models\LoginSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginSession>
 */
class LoginSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'logged_in_at' => now(),
            'last_active_at' => now(),
        ];
    }
}
