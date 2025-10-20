<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        $balance = fake()->randomFloat(2, 0, 10000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Checking Account', 'Savings Account', 'Credit Card']),
            'type' => fake()->randomElement(['checking', 'savings', 'credit_card', 'investment']),
            'balance' => $balance,
            'cleared_balance' => $balance,
            'uncleared_balance' => 0,
            'closed' => false,
        ];
    }

    public function checking(): static
    {
        return $this->state([
            'type' => 'checking',
            'name' => 'Checking Account',
        ]);
    }

    public function creditCard(): static
    {
        return $this->state([
            'type' => 'credit_card',
            'name' => 'Credit Card',
            'balance' => fake()->randomFloat(2, -5000, 0),
        ]);
    }
}
