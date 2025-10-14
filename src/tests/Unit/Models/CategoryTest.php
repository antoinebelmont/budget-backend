<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Goal;
use App\Models\Transaction;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    public function test_category_belongs_to_category_group()
    {
        $categoryGroup = CategoryGroup::factory()->create();
        $category = Category::factory()->create(['category_group_id' => $categoryGroup->id]);

        $this->assertTrue($category->categoryGroup->is($categoryGroup));
    }

    public function test_category_updates_activity_correctly()
    {
        $category = Category::factory()->create(['budgeted' => 500]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'amount' => -100
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'amount' => -50
        ]);

        $category->updateActivity();

        $this->assertEquals(-150, $category->activity);
        $this->assertEquals(350, $category->available);
    }

    public function test_category_can_have_goals()
    {
        $category = Category::factory()->create();
        $goal = Goal::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->goals->contains($goal));
    }
}
