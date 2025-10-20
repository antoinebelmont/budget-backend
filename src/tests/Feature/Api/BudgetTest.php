<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    public function test_user_can_view_budget()
    {
        $data = $this->createUserWithBudget();
        $this->authenticatedUser($data['user']);

        $response = $this->getJson('/api/budget');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'month',
                'category_groups' => [
                    '*' => [
                        'id', 'name', 'sort_order',
                        'categories' => [
                            '*' => ['id', 'name', 'budgeted', 'activity', 'available']
                        ]
                    ]
                ]
            ]);
    }

    public function test_user_can_update_category_budget()
    {
        $data = $this->createUserWithBudget();
        $this->authenticatedUser($data['user']);

        $response = $this->putJson("/api/categories/{$data['rent']->id}/budget", [
            'budgeted' => 1200
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['budgeted' => 1200]);

        $this->assertDatabaseHas('categories', [
            'id' => $data['rent']->id,
            'budgeted' => 1200
        ]);
    }

    public function test_budget_update_validation()
    {
        $data = $this->createUserWithBudget();           // Create user with categories
        $this->authenticatedUser($data['user']);         // Authenticate THAT user

        // Now use THEIR category for validation testing
        $response = $this->putJson("/api/categories/{$data['rent']->id}/budget", [
            'budgeted' => 'not-a-number'
        ]);

        $response->assertStatus(422)  // Now gets validation error, not auth error
        ->assertJsonValidationErrors(['budgeted']);
    }
    public function test_budget_update_validation_with_valid_user()
    {
        $data = $this->createUserWithBudget();
        $this->authenticatedUser($data['user']);

        // Test validation with user's own category
        $response = $this->putJson("/api/categories/{$data['rent']->id}/budget", [
            'budgeted' => 'invalid-number'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budgeted']);
    }

    public function test_budget_update_validation_with_negative_number()
    {
        $data = $this->createUserWithBudget();
        $this->authenticatedUser($data['user']);

        $response = $this->putJson("/api/categories/{$data['rent']->id}/budget", [
            'budgeted' => -100  // Negative number should fail
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budgeted']);
    }

    public function test_budget_update_validation_with_missing_field()
    {
        $data = $this->createUserWithBudget();
        $this->authenticatedUser($data['user']);

        $response = $this->putJson("/api/categories/{$data['rent']->id}/budget", [
            // Missing 'budgeted' field
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budgeted']);
    }

    public function test_budget_update_authorization_vs_validation_order()
    {
        // Create two users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create category for user2
        $categoryGroup = $user2->categoryGroups()->create(['name' => 'Test Group', 'sort_order' => 1]);
        $category = $categoryGroup->categories()->create([
            'name' => 'Test Category',
            'user_id' => $user2->id,
            'budgeted' => 100
        ]);

        // Authenticate as user1
        $this->authenticatedUser($user1);

        // Try to update user2's category with invalid data
        // Should get 403 (authorization) before 422 (validation)
        $response = $this->putJson("/api/categories/{$category->id}/budget", [
            'budgeted' => 'invalid-data'
        ]);

        $response->assertStatus(403); // Authorization happens first
    }
}
