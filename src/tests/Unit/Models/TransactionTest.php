<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    public function test_transaction_belongs_to_user()
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);
        $this->assertTrue($transaction->user->is($user));
    }

    public function test_transaction_belongs_to_account()
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create(['account_id' => $account->id]);

        $this->assertTrue($transaction->account->is($account));
    }

    public function test_transaction_is_income_attribute()
    {
        $incomeTransaction = Transaction::factory()->make(['amount' => 1000, 'is_income' => true]);
        $expenseTransaction = Transaction::factory()->make(['amount' => -100, 'is_income' => false]);

        $this->assertTrue($incomeTransaction->is_income);
        $this->assertFalse($expenseTransaction->is_income);
    }

    public function test_transaction_is_expense_attribute()
    {
        $incomeTransaction = Transaction::factory()->make(['amount' => 1000, 'is_expense' => false]);
        $expenseTransaction = Transaction::factory()->make(['amount' => -100, 'is_expense' => true]);
        $this->assertFalse($incomeTransaction->is_expense);
        $this->assertTrue($expenseTransaction->is_expense);
    }

    public function test_transaction_updates_account_balance_on_save()
    {
        $account = Account::factory()->create(['balance' => 1000]);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => $account->balance,
            'cleared' => 'cleared'
        ])->fresh();

        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => -100,
            'cleared' => 'cleared'
        ])->fresh();

        $account->updateBalance();

        $this->assertEquals(900, $account->fresh()->balance);
    }
}
