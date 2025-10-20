<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'payee_id' => Payee::factory(),
            'date' => fake()->dateTimeBetween('-6 months', 'now'),
            'amount' => fake()->randomFloat(2, -500, 500),
            'memo' => fake()->optional()->sentence(),
            'cleared' => fake()->randomElement(['cleared', 'uncleared', 'reconciled']),
            'approved' => true,
            'import_id' => fake()->optional()->md5(),
        ];
    }

    public function income(): static
    {
        return $this->state([
            'amount' => fake()->randomFloat(2, 100, 5000),
            'category_id' => null,
        ]);
    }

    public function expense(): static
    {
        return $this->state([
            'amount' => fake()->randomFloat(2, -500, -10),
        ]);
    }
}
