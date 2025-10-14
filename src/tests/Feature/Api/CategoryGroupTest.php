<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\User;
use Tests\TestCase;

class CategoryGroupTest extends TestCase
{
    public function test_user_can_list_their_category_groups()
    {
        $user = $this->authenticatedUser();
        $groups = CategoryGroup::factory()->count(3)->create(['user_id' => $user->id]);

        foreach ($groups as $group) {
            Category::factory()->count(2)->create([
                'user_id' => $user->id,
                'category_group_id' => $group->id
            ]);
        }

        $response = $this->getJson('/api/category-groups');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'category_groups');
    }

    public function test_user_can_create_category_group()
    {
        $user = $this->authenticatedUser();

        $groupData = [
            'name' => 'New Category Group',
            'sort_order' => 5
        ];

        $response = $this->postJson('/api/category-groups', $groupData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Category Group']);

        $this->assertDatabaseHas('category_groups', [
            'user_id' => $user->id,
            'name' => 'New Category Group',
            'sort_order' => 5
        ]);
    }

    public function test_user_can_reorder_category_groups()
    {
        $user = $this->authenticatedUser();
        $response = $this->putJson('/api/category-groups/reorder', [
            'category_groups' => []
        ]);
        $group1 = CategoryGroup::factory()->create(['user_id' => $user->id, 'sort_order' => 1]);
        $group2 = CategoryGroup::factory()->create(['user_id' => $user->id, 'sort_order' => 2]);

        $reorderData = [
            'category_groups' => [
                ['id' => $group2->id, 'sort_order' => 1],
                ['id' => $group1->id, 'sort_order' => 2],
            ]
        ];

        $response = $this->putJson('/api/category-groups/reorder', $reorderData);
        $response->assertStatus(200);

        $this->assertEquals(1, $group2->fresh()->sort_order);
        $this->assertEquals(2, $group1->fresh()->sort_order);
    }

    public function test_user_can_update_category_group()
    {
        $user = $this->authenticatedUser();
        $group = CategoryGroup::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson("/api/category-groups/{$group->id}", [
            'name' => 'Updated Group Name'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Group Name']);
    }

    public function test_user_cannot_delete_category_group_with_categories()
    {
        $user = $this->authenticatedUser();
        $group = CategoryGroup::factory()->create(['user_id' => $user->id]);
        Category::factory()->create([
            'user_id' => $user->id,
            'category_group_id' => $group->id
        ]);

        $response = $this->deleteJson("/api/category-groups/{$group->id}");

        $response->assertStatus(422);
    }

    public function test_user_can_delete_empty_category_group()
    {
        $user = $this->authenticatedUser();
        $group = CategoryGroup::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/category-groups/{$group->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($group);
    }
}
