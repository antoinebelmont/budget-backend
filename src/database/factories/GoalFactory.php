<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'type' => fake()->randomElement(['target_balance', 'target_date', 'monthly_funding']),
            'target_amount' => fake()->randomFloat(2, 500, 10000),
            'target_date' => fake()->dateTimeBetween('now', '+2 years'),
            'monthly_amount' => fake()->randomFloat(2, 50, 500),
        ];
    }

    public function targetBalance(): static
    {
        return $this->state([
            'type' => 'target_balance',
            'target_date' => null,
            'monthly_amount' => null,
        ]);
    }
}
