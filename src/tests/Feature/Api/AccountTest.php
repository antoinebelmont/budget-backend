<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_user_can_list_their_accounts()
    {
        $user = $this->authenticatedUser();
        Account::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'accounts')
            ->assertJsonStructure([
                'accounts' => [
                    '*' => ['id', 'name', 'type', 'balance', 'closed']
                ]
            ]);
    }

    public function test_user_cannot_see_other_users_accounts()
    {
        $user = $this->authenticatedUser();
        $otherUser = User::factory()->create();

        Account::factory()->create(['user_id' => $user->id]);
        Account::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'accounts');
    }

    public function test_user_can_create_account()
    {
        $user = $this->authenticatedUser();

        $accountData = [
            'name' => 'New Savings Account',
            'type' => 'savings',
            'balance' => 1000.50
        ];

        $response = $this->postJson('/api/accounts', $accountData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Savings Account']);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'New Savings Account',
            'type' => 'savings',
            'balance' => 1000.50
        ]);
    }

    public function test_account_creation_validation()
    {
        $this->authenticatedUser();

        $response = $this->postJson('/api/accounts', [
            'name' => '',
            'type' => 'invalid_type',
            'balance' => 'not_a_number'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'balance']);
    }

    public function test_user_can_view_specific_account()
    {
        $user = $this->authenticatedUser();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Transaction::factory()->count(5)->create(['account_id' => $account->id, 'user_id' => $user->id]);

        $response = $this->getJson("/api/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'account' => [
                    'id', 'name', 'type', 'balance',
                    'transactions' => [
                        '*' => ['id', 'date', 'amount', 'memo']
                    ]
                ]
            ]);
    }

    public function test_user_cannot_view_other_users_account()
    {
        $user = $this->authenticatedUser();
        $otherUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/accounts/{$account->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_account()
    {
        $user = $this->authenticatedUser();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'name' => 'Updated Account Name'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Account Name']);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Account Name'
        ]);
    }

    public function test_user_can_delete_account()
    {
        $user = $this->authenticatedUser();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($account);
    }
}
