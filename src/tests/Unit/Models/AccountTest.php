<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_account_belongs_to_user()
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($account->user->is($user));
    }

    public function test_account_updates_balance_correctly()
    {
        $account = Account::factory()->create(['balance' => 0]);

        // Create cleared transactions
        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 1000,
            'cleared' => 'cleared'
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => -200,
            'cleared' => 'cleared'
        ]);

        // Create uncleared transaction
        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => -50,
            'cleared' => 'uncleared'
        ]);

        $account->updateBalance();

        $this->assertEquals(800, $account->cleared_balance);
        $this->assertEquals(-50, $account->uncleared_balance);
        $this->assertEquals(750, $account->balance);
    }

    public function test_account_factory_creates_checking_account()
    {
        $account = Account::factory()->checking()->create();

        $this->assertEquals('checking', $account->type);
        $this->assertEquals('Checking Account', $account->name);
    }

    public function test_account_factory_creates_credit_card()
    {
        $account = Account::factory()->creditCard()->create();

        $this->assertEquals('credit_card', $account->type);
        $this->assertLessThanOrEqual(0, $account->balance);
    }
}
