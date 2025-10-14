<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use App\Models\Account;
use App\Models\CategoryGroup;
use App\Models\Category;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.expiration' => null]);
        config(['mail.default' => 'array']);
        config(['cache.default' => 'array']);
    }

    protected function authenticatedUser($user = null)
    {
        $user = $user ?: User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    protected function createUserWithBudget()
    {
        $user = User::factory()->create();

        // Create default category groups
        $immediateObligations = $user->categoryGroups()->create([
            'name' => 'Immediate Obligations',
            'sort_order' => 1,
        ]);

        $trueExpenses = $user->categoryGroups()->create([
            'name' => 'True Expenses',
            'sort_order' => 2,
        ]);

        // Create categories
        $rent = $immediateObligations->categories()->create([
            'name' => 'Rent',
            'user_id' => $user->id,
            'budgeted' => 1000,
            'activity' => 0,
            'available' => 1000,
        ]);

        $carMaintenance = $trueExpenses->categories()->create([
            'name' => 'Car Maintenance',
            'user_id' => $user->id,
            'budgeted' => 200,
            'activity' => 0,
            'available' => 200,
        ]);

        // Create checking account
        $account = $user->accounts()->create([
            'name' => 'Checking Account',
            'type' => 'checking',
            'balance' => 2000,
            'cleared_balance' => 2000,
            'uncleared_balance' => 0,
        ]);

        return compact('user', 'immediateObligations', 'trueExpenses', 'rent', 'carMaintenance', 'account');
    }
}
