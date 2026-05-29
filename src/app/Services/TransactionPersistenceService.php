<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionPersistenceService
{
    public function persistBatch(array $resolvedRows, int $accountId, int $userId): array
    {
        return DB::transaction(function () use ($resolvedRows, $accountId, $userId) {
            $ids = [];

            foreach ($resolvedRows as $row) {
                $validatedRow = $row['validated_row'];
                $payeeId = $row['payee_id'];
                $categoryId = $row['category_id'] ?? null;

                $transaction = new Transaction();
                $transaction->fill([
                    'user_id' => $userId,
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'payee_id' => $payeeId,
                    'date' => $validatedRow->date,
                    'amount'    => $validatedRow->is_expense ? -$validatedRow->amount : $validatedRow->amount,
                    'memo' => $validatedRow->memo,
                    'cleared' => 'uncleared',
                    'approved' => true,
                    'is_expense' => $validatedRow->is_expense,
                ]);
                $transaction->is_income = !$validatedRow->is_expense;
                $transaction->import_id = (string) Str::uuid();
                $transaction->save();

                $ids[] = $transaction->id;
            }

            return $ids;
        });
    }
}
