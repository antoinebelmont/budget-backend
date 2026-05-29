<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    public function test_imports_csv_successfully(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->createWithContent(
            'transactions.csv',
            "date,payee,memo,amount\n2024-01-15,Store,Groceries,45.50\n2024-01-16,Amazon,Books,25.00",
        );

        $response = $this->actingAs($user)->post('/api/transactions/import', [
            'file' => $file,
            'account_id' => $account->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'imported_count' => 2,
            'ignored_rows' => [],
            'error_rows' => [],
            'success' => true,
        ]);
    }

    public function test_returns_validation_error_when_no_file(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/api/transactions/import', [
            'account_id' => $account->id,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_returns_validation_error_with_non_csv_file(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->actingAs($user)->post('/api/transactions/import', [
            'file' => $file,
            'account_id' => $account->id,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_returns_error_with_wrong_account_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);

        $file = UploadedFile::fake()->createWithContent(
            'transactions.csv',
            "date,payee,memo,amount\n2024-01-15,Store,Groceries,45.50",
        );

        $response = $this->actingAs($user)->post('/api/transactions/import', [
            'file' => $file,
            'account_id' => $otherAccount->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_returns_unauthorized_without_auth(): void
    {
        $account = Account::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'transactions.csv',
            "date,payee,memo,amount\n2024-01-15,Store,Groceries,45.50",
        );

        $response = $this->post('/api/transactions/import', [
            'file' => $file,
            'account_id' => $account->id,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }
}
