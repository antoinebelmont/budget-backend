<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionPersistenceService;
use App\Services\ValidatedRow;
use Tests\TestCase;

class TransactionPersistenceServiceTest extends TestCase
{

    public function test_persists_all_rows_successfully(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $payee1 = Payee::factory()->create(['user_id' => $user->id]);
        $payee2 = Payee::factory()->create(['user_id' => $user->id]);

        $resolvedRows = [
            [
                'validated_row' => new ValidatedRow(
                    date: '2025-01-15',
                    payee: 'Amazon',
                    memo: 'Books',
                    amount: 45.99,
                    is_expense: true,
                ),
                'payee_id' => $payee1->id,
            ],
            [
                'validated_row' => new ValidatedRow(
                    date: '2025-01-16',
                    payee: 'Freelance Client',
                    memo: 'Invoice #123',
                    amount: 500.00,
                    is_expense: false,
                ),
                'payee_id' => $payee2->id,
            ],
        ];

        $service = new TransactionPersistenceService();
        $ids = $service->persistBatch($resolvedRows, $account->id, $user->id);

        $this->assertCount(2, $ids);
        $this->assertDatabaseHas('transactions', ['id' => $ids[0]]);
        $this->assertDatabaseHas('transactions', ['id' => $ids[1]]);
    }

    public function test_created_records_have_correct_values(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $payee1 = Payee::factory()->create(['user_id' => $user->id]);
        $payee2 = Payee::factory()->create(['user_id' => $user->id]);

        $resolvedRows = [
            [
                'validated_row' => new ValidatedRow(
                    date: '2025-01-15',
                    payee: 'Amazon',
                    memo: 'Books',
                    amount: 45.99,
                    is_expense: true,
                ),
                'payee_id' => $payee1->id,
            ],
            [
                'validated_row' => new ValidatedRow(
                    date: '2025-01-16',
                    payee: 'Freelance Client',
                    memo: 'Invoice #123',
                    amount: 500.00,
                    is_expense: false,
                ),
                'payee_id' => $payee2->id,
            ],
        ];

        $service = new TransactionPersistenceService();
        $ids = $service->persistBatch($resolvedRows, $account->id, $user->id);

        $this->assertDatabaseHas('transactions', [
            'id' => $ids[0],
            'date' => '2025-01-15 00:00:00',
            'amount' => -45.99,
            'memo' => 'Books',
            'cleared' => 'uncleared',
            'is_expense' => 1,
            'is_income' => 0,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $ids[1],
            'date' => '2025-01-16 00:00:00',
            'amount' => 500.00,
            'memo' => 'Invoice #123',
            'cleared' => 'uncleared',
            'is_expense' => 0,
            'is_income' => 1,
        ]);

        $t1 = Transaction::find($ids[0]);
        $t2 = Transaction::find($ids[1]);

        $this->assertNotNull($t1->import_id);
        $this->assertNotNull($t2->import_id);
        $this->assertNotEquals($t1->import_id, $t2->import_id);
    }

    public function test_creates_transactions_with_null_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $payee = Payee::factory()->create(['user_id' => $user->id]);

        $resolvedRows = [
            [
                'validated_row' => new ValidatedRow(
                    date: '2025-02-01',
                    payee: 'Store',
                    memo: 'Groceries',
                    amount: 89.50,
                    is_expense: true,
                ),
                'payee_id' => $payee->id,
            ],
        ];

        $service = new TransactionPersistenceService();
        $ids = $service->persistBatch($resolvedRows, $account->id, $user->id);

        $this->assertDatabaseHas('transactions', [
            'id' => $ids[0],
            'category_id' => null,
        ]);
    }
}
