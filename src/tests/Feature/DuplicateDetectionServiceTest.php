<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use App\Services\ValidatedRow;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    private DuplicateDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DuplicateDetectionService;
    }

    public function test_intra_file_duplicates_are_detected(): void
    {
        $rows = [
            new ValidatedRow('2024-01-15', 'Amazon', 'books', 25.50, true),
            new ValidatedRow('2024-01-15', 'Amazon', 'books', 25.50, true),
            new ValidatedRow('2024-01-15', 'Amazon', 'books', 25.50, true),
        ];

        $result = $this->service->partition($rows, 1);

        $this->assertCount(1, $result['unique_rows']);
        $this->assertCount(2, $result['duplicate_rows']);
        $this->assertSame(2, $result['duplicate_rows'][0]['row_number']);
        $this->assertSame(3, $result['duplicate_rows'][1]['row_number']);
        $this->assertStringContainsString('Duplicate of row 1', $result['duplicate_rows'][0]['reason']);
    }

    public function test_cross_file_duplicates_are_detected(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $payee = Payee::factory()->create(['user_id' => $user->id, 'name' => 'Amazon']);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'payee_id' => $payee->id,
            'date' => '2024-01-15',
            'amount' => -25.50,
            'user_id' => $user->id,
        ]);

        $rows = [
            new ValidatedRow('2024-01-15', 'Amazon', 'books', 25.50, true),
        ];

        $result = $this->service->partition($rows, $account->id);

        $this->assertCount(0, $result['unique_rows']);
        $this->assertCount(1, $result['duplicate_rows']);
        $this->assertSame(1, $result['duplicate_rows'][0]['row_number']);
        $this->assertStringContainsString('existing transaction', $result['duplicate_rows'][0]['reason']);
    }

    public function test_unique_rows_pass_through(): void
    {
        $rows = [
            new ValidatedRow('2024-01-15', 'Amazon', 'books', 25.50, true),
            new ValidatedRow('2024-01-16', 'Walmart', 'groceries', 45.00, true),
        ];

        $result = $this->service->partition($rows, 1);

        $this->assertCount(2, $result['unique_rows']);
        $this->assertCount(0, $result['duplicate_rows']);
    }

    public function test_mixed_batch_with_all_types(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $payee = Payee::factory()->create(['user_id' => $user->id, 'name' => 'Existing Co']);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'payee_id' => $payee->id,
            'date' => '2024-01-10',
            'amount' => -100.00,
            'user_id' => $user->id,
        ]);

        $rows = [
            new ValidatedRow('2024-01-10', 'Existing Co', 'invoice', 100.00, true),
            new ValidatedRow('2024-01-15', 'Amazon', 'books', 25.50, true),
            new ValidatedRow('2024-01-15', 'Amazon', 'books', 25.50, true),
            new ValidatedRow('2024-01-20', 'Walmart', 'groceries', 45.00, true),
        ];

        $result = $this->service->partition($rows, $account->id);

        $this->assertCount(2, $result['unique_rows']);
        $this->assertCount(2, $result['duplicate_rows']);

        $this->assertSame(1, $result['duplicate_rows'][0]['row_number']);
        $this->assertStringContainsString('existing transaction', $result['duplicate_rows'][0]['reason']);

        $this->assertSame(3, $result['duplicate_rows'][1]['row_number']);
        $this->assertStringContainsString('Duplicate of row 2', $result['duplicate_rows'][1]['reason']);

        $this->assertSame('Amazon', $result['unique_rows'][0]->payee);
        $this->assertSame('Walmart', $result['unique_rows'][1]->payee);
    }
}
