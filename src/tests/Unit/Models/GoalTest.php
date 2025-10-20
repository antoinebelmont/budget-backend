<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use Tests\TestCase;

class GoalTest extends TestCase
{
    public function test_goal_belongs_to_category()
    {
        $category = Category::factory()->create();
        $goal = Goal::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($goal->category->is($category));
    }

    public function test_goal_calculates_progress_percentage()
    {
        $category = Category::factory()->create();
        $category->budgeted = 250;
        $category->save();

        $goal = Goal::factory()->create([
            'category_id' => $category->id,
            'target_amount' => 1000
        ]);

        $this->assertEquals(25, $goal->progress_percentage);
    }

    public function test_goal_calculates_remaining_amount()
    {
        $category = Category::factory()->create();
        $category->budgeted = 250;
        $category->save();
        $goal = Goal::factory()->create([
            'category_id' => $category->id,
            'target_amount' => 1000
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'amount' => 100,
            'cleared' => 'cleared',
            'goal_id' => $goal->id
        ]);

        $this->assertEquals(650, $goal->remaining_amount);
    }

    public function test_goal_calculates_suggested_monthly_amount()
    {
        $category = Category::factory()->create();
        $category->budgeted = 200;
        $category->save();
        $goal = Goal::factory()->create([
            'category_id' => $category->id,
            'target_amount' => 1000,
            'target_date' => now()->addMonths(4)
        ])->fresh();
        $this->assertEquals(200, $goal->suggested_monthly_amount);
    }
}
