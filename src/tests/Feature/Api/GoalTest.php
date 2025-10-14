<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Goal;
use App\Models\User;
use Tests\TestCase;

class GoalTest extends TestCase
{
    public function test_user_can_list_their_goals()
    {
        $user = $this->authenticatedUser();
        $categories = Category::factory()->count(3)->create(['user_id' => $user->id]);

        foreach ($categories as $category) {
            Goal::factory()->create(['category_id' => $category->id]);
        }

        $response = $this->getJson('/api/goals');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'goals')
            ->assertJsonStructure([
                'goals' => [
                    '*' => ['id', 'type', 'target_amount', 'category']
                ]
            ]);
    }

    public function test_user_can_create_goal()
    {
        $user = $this->authenticatedUser();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $goalData = [
            'category_id' => $category->id,
            'type' => 'target_balance',
            'target_amount' => 5000.00
        ];

        $response = $this->postJson('/api/goals', $goalData);

        $response->assertStatus(201)
            ->assertJsonFragment(['target_amount' => '5000.00']);

        $this->assertDatabaseHas('goals', [
            'category_id' => $category->id,
            'type' => 'target_balance',
            'target_amount' => 5000.00
        ]);
    }

    public function test_user_can_create_target_date_goal()
    {
        $user = $this->authenticatedUser();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $goalData = [
            'category_id' => $category->id,
            'type' => 'target_date',
            'target_amount' => 2000.00,
            'target_date' => '2025-12-31'
        ];

        $response = $this->postJson('/api/goals', $goalData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('goals', [
            'category_id' => $category->id,
            'type' => 'target_date',
            'target_date' => '2025-12-31'
        ]);
    }

    public function test_goal_creation_validation()
    {
        $this->authenticatedUser();

        $response = $this->postJson('/api/goals', [
            'category_id' => 99999,
            'type' => 'invalid_type',
            'target_amount' => 'not-a-number'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'type']);
    }

    public function test_user_cannot_create_multiple_goals_for_same_category()
    {
        $user = $this->authenticatedUser();
        $category = Category::factory()->create(['user_id' => $user->id]);
        Goal::factory()->create(['category_id' => $category->id]);

        $response = $this->postJson('/api/goals', [
            'category_id' => $category->id,
            'type' => 'target_balance',
            'target_amount' => 1000
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_update_goal()
    {
        $user = $this->authenticatedUser();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->create(['category_id' => $category->id]);

        $response = $this->putJson("/api/goals/{$goal->id}", [
            'target_amount' => 7500.00
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['target_amount' => '7500.00']);
    }

    public function test_user_can_delete_goal()
    {
        $user = $this->authenticatedUser();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/api/goals/{$goal->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($goal);
    }
}
