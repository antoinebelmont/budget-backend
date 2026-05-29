<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\ImportOrchestrator;
use App\Services\ImportResult;
use Tests\TestCase;

class ImportOrchestratorTest extends TestCase
{
    private ImportOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(ImportOrchestrator::class);
    }

    public function test_valid_csv_imports_all_rows(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $csv = "date,payee,memo,amount\n2024-01-15,Store,Groceries,45.50\n2024-01-16,Amazon,Books,25.00";

        $result = $this->orchestrator->import($csv, $account->id, $user);

        $this->assertInstanceOf(ImportResult::class, $result);
        $this->assertSame(2, $result->imported_count);
        $this->assertEmpty($result->ignored_rows);
        $this->assertEmpty($result->error_rows);
        $this->assertTrue($result->success);
    }

    public function test_csv_with_duplicates_ignores_duplicate_rows(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $csv = "date,payee,memo,amount\n2024-01-15,Store,Groceries,45.50\n2024-01-15,Store,Groceries,45.50\n2024-01-16,Amazon,Books,25.00";

        $result = $this->orchestrator->import($csv, $account->id, $user);

        $this->assertSame(2, $result->imported_count);
        $this->assertCount(1, $result->ignored_rows);
        $this->assertSame(2, $result->ignored_rows[0]['row_number']);
        $this->assertStringContainsString('Duplicate', $result->ignored_rows[0]['reason']);
        $this->assertEmpty($result->error_rows);
        $this->assertTrue($result->success);
    }

    public function test_csv_with_invalid_date_returns_error_rows(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $csv = "date,payee,memo,amount\nnot-a-date,Store,Groceries,45.50";

        $result = $this->orchestrator->import($csv, $account->id, $user);

        $this->assertSame(0, $result->imported_count);
        $this->assertEmpty($result->ignored_rows);
        $this->assertNotEmpty($result->error_rows);
        $this->assertSame(1, $result->error_rows[0]['row_number']);
        $this->assertStringContainsString('Unparseable date', $result->error_rows[0]['reason']);
        $this->assertFalse($result->success);
    }

    public function test_csv_with_invalid_encoding_returns_error(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $content = "\xFF\xFE\x00\x00";

        $result = $this->orchestrator->import($content, $account->id, $user);

        $this->assertSame(0, $result->imported_count);
        $this->assertEmpty($result->ignored_rows);
        $this->assertNotEmpty($result->error_rows);
        $this->assertFalse($result->success);
    }

    public function test_empty_file_returns_error(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $result = $this->orchestrator->import('', $account->id, $user);

        $this->assertSame(0, $result->imported_count);
        $this->assertEmpty($result->ignored_rows);
        $this->assertNotEmpty($result->error_rows);
        $this->assertSame(1, $result->error_rows[0]['row_number']);
        $this->assertFalse($result->success);
    }
}
