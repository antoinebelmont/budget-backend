<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'preferences' => [
                'currency_format' => 'symbol_before',
                'date_format' => 'Y-m-d',
                'notifications' => [
                    'budget_warnings' => true,
                    'goal_reminders' => true,
                ],
                'theme' => 'light',
            ],
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
