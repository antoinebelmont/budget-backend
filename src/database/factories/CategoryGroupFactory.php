<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Immediate Obligations',
                'True Expenses',
                'Quality of Life Goals',
                'Just for Fun'
            ]),
            'hidden' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
