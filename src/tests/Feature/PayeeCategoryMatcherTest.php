<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Services\PayeeCategoryMatcher;
use App\Services\ValidatedRow;
use Tests\TestCase;

class PayeeCategoryMatcherTest extends TestCase
{
    private PayeeCategoryMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = app(PayeeCategoryMatcher::class);
    }

    public function test_existing_payee_with_category_history_returns_that_category(): void
    {
        $data = $this->createUserWithBudget();
        $user = $data['user'];

        $payee = Payee::factory()->create([
            'user_id' => $user->id,
            'name' => 'Supermarket',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'payee_id' => $payee->id,
            'category_id' => $data['rent']->id,
            'account_id' => $data['account']->id,
        ]);

        $row = new ValidatedRow(
            date: '2025-01-15',
            payee: 'Supermarket',
            memo: '',
            amount: 100.00,
            is_expense: true,
        );

        $result = $this->matcher->resolve($row, $user);

        $this->assertSame($payee->id, $result['payee_id']);
        $this->assertSame($data['rent']->id, $result['category_id']);
    }

    public function test_existing_payee_with_auto_assign_category_id_uses_that(): void
    {
        $data = $this->createUserWithBudget();
        $user = $data['user'];

        $payee = Payee::factory()->create([
            'user_id' => $user->id,
            'name' => 'AutoAssignCo',
            'auto_assign_category_id' => $data['carMaintenance']->id,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'payee_id' => $payee->id,
            'category_id' => $data['rent']->id,
            'account_id' => $data['account']->id,
        ]);

        $row = new ValidatedRow(
            date: '2025-01-15',
            payee: 'AutoAssignCo',
            memo: '',
            amount: 50.00,
            is_expense: true,
        );

        $result = $this->matcher->resolve($row, $user);

        $this->assertSame($payee->id, $result['payee_id']);
        $this->assertSame($data['carMaintenance']->id, $result['category_id']);
    }

    public function test_existing_payee_with_multiple_categories_picks_most_frequent(): void
    {
        $data = $this->createUserWithBudget();
        $user = $data['user'];

        $payee = Payee::factory()->create([
            'user_id' => $user->id,
            'name' => 'FrequentShop',
        ]);

        $extraCategory = Category::factory()->create([
            'user_id' => $user->id,
            'category_group_id' => $data['immediateObligations']->id,
            'name' => 'Groceries',
        ]);

        // 3 transactions with extraCategory, 1 with rent
        foreach (range(1, 3) as $i) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'payee_id' => $payee->id,
                'category_id' => $extraCategory->id,
                'account_id' => $data['account']->id,
            ]);
        }

        Transaction::factory()->create([
            'user_id' => $user->id,
            'payee_id' => $payee->id,
            'category_id' => $data['rent']->id,
            'account_id' => $data['account']->id,
        ]);

        $row = new ValidatedRow(
            date: '2025-01-15',
            payee: 'FrequentShop',
            memo: '',
            amount: 75.00,
            is_expense: true,
        );

        $result = $this->matcher->resolve($row, $user);

        $this->assertSame($payee->id, $result['payee_id']);
        $this->assertSame($extraCategory->id, $result['category_id']);
    }

    public function test_existing_payee_without_category_history_returns_null_category(): void
    {
        $data = $this->createUserWithBudget();
        $user = $data['user'];

        $payee = Payee::factory()->create([
            'user_id' => $user->id,
            'name' => 'NewVendor',
        ]);

        $row = new ValidatedRow(
            date: '2025-01-15',
            payee: 'NewVendor',
            memo: '',
            amount: 200.00,
            is_expense: true,
        );

        $result = $this->matcher->resolve($row, $user);

        $this->assertSame($payee->id, $result['payee_id']);
        $this->assertNull($result['category_id']);
    }

    public function test_new_payee_creates_record_and_returns_null_category(): void
    {
        $data = $this->createUserWithBudget();
        $user = $data['user'];

        $row = new ValidatedRow(
            date: '2025-01-15',
            payee: '  Brand New Store  ',
            memo: '',
            amount: 150.00,
            is_expense: true,
        );

        $result = $this->matcher->resolve($row, $user);

        $this->assertNotNull($result['payee_id']);
        $this->assertNull($result['category_id']);

        $this->assertDatabaseHas('payees', [
            'id' => $result['payee_id'],
            'user_id' => $user->id,
            'name' => 'Brand New Store',
        ]);
    }
}
