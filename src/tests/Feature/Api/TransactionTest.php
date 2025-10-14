<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    public function test_user_can_list_their_transactions()
    {
        $data = $this->createUserWithBudget();
        $this->authenticatedUser($data['user']);

        Transaction::factory()->count(10)->create([
            'user_id' => $data['user']->id,
            'account_id' => $data['account']->id
        ]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'date', 'amount', 'memo', 'cleared', 'account', 'category', 'payee']
                ],
                'links'
            ]);
    }

    public function test_user_can_filter_transactions_by_account()
    {
        $data = $this->createUserWithBudget();
        $user = $this->authenticatedUser($data['user']);

        $account1 = $data['account'];
        $account2 = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(5)->create(['account_id' => $account1->id, 'user_id' => $user->id]);
        Transaction::factory()->count(3)->create(['account_id' => $account2->id, 'user_id' => $user->id]);

        $response = $this->getJson("/api/transactions?account_id={$account1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_user_can_create_transaction()
    {
        $data = $this->createUserWithBudget();
        $user = $this->authenticatedUser($data['user']);

        $payee = Payee::factory()->create(['user_id' => $user->id]);

        $transactionData = [
            'account_id' => $data['account']->id,
            'date' => '2024-01-15',
            'amount' => -150.75,
            'payee_id' => $payee->id,
            'category_id' => $data['rent']->id,
            'memo' => 'Test transaction',
            'cleared' => 'cleared'
        ];

        $response = $this->postJson('/api/transactions', $transactionData);

        $response->assertStatus(201)
            ->assertJsonFragment(['amount' => '-150.75']);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $data['account']->id,
            'amount' => -150.75,
            'memo' => 'Test transaction'
        ]);
    }

    public function test_transaction_creation_validation()
    {
        $user = $this->authenticatedUser();

        $response = $this->postJson('/api/transactions', [
            'account_id' => 99999,
            'date' => 'invalid-date',
            'amount' => 'not-a-number'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id', 'date', 'amount']);
    }

    public function test_user_can_update_transaction()
    {
        $data = $this->createUserWithBudget();
        $user = $this->authenticatedUser($data['user']);

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $data['account']->id,
            'amount' => -100
        ]);

        $response = $this->putJson("/api/transactions/{$transaction->id}", [
            'amount' => -150,
            'memo' => 'Updated memo'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['memo' => 'Updated memo']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => -150,
            'memo' => 'Updated memo'
        ]);
    }

    public function test_user_can_delete_transaction()
    {
        $data = $this->createUserWithBudget();
        $user = $this->authenticatedUser($data['user']);

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $data['account']->id
        ]);

        $response = $this->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($transaction);
    }
}
