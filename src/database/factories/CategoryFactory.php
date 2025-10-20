<?php

namespace Database\Factories;

use App\Models\CategoryGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $budgeted = fake()->randomFloat(2, 0, 1000);

        return [
            'user_id' => User::factory(),
            'category_group_id' => CategoryGroup::factory(),
            'name' => fake()->randomElement([
                'Rent/Mortgage', 'Groceries', 'Gas', 'Electric',
                'Car Maintenance', 'Medical', 'Dining Out', 'Entertainment'
            ]),
            'budgeted' => $budgeted,
            'color' => fake()->hexColor(),
            'hidden' => false,
        ];
    }
}
