<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_user_has_full_name_attribute()
    {
        $user = User::factory()->make([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);

        $this->assertEquals('John Doe', $user->full_name);
    }

    public function test_user_can_update_last_login()
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $user->updateLastLogin();
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_gets_default_preferences()
    {
        $user = new User();
        $preferences = $user->getDefaultPreferences();

        $this->assertIsArray($preferences);
        $this->assertArrayHasKey('currency_format', $preferences);
        $this->assertArrayHasKey('notifications', $preferences);
    }

    public function test_user_has_accounts_relationship()
    {
        $user = User::factory()->create();
        $account = $user->accounts()->create([
            'name' => 'Test Account',
            'type' => 'checking',
            'balance' => 1000,
        ]);

        $this->assertTrue($user->accounts->contains($account));
    }

    public function test_user_has_categories_relationship()
    {
        $user = User::factory()->create();
        $categoryGroup = $user->categoryGroups()->create([
            'name' => 'Test Group',
            'sort_order' => 1,
        ]);
        $category = $user->categories()->create([
            'name' => 'Test Category',
            'category_group_id' => $categoryGroup->id,
        ]);

        $this->assertTrue($user->categories->contains($category));
    }
}
