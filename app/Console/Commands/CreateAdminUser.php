<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an admin user interactively';

    public function handle(): int
    {
        $name = $this->ask('Admin name');
        $email = $this->ask('Admin email');
        $password = $this->secret('Password');

        if (! $name || ! $email || ! $password) {
            $this->error('All fields are required.');

            return Command::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return Command::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Admin user {$email} created successfully.");

        return Command::SUCCESS;
    }
}
