<?php

namespace App\Services;

use App\Models\Transaction;

class DuplicateDetectionService
{
    /**
     * Partition a batch of ValidatedRow objects into unique and duplicate sets.
     *
     * Intra-file duplicates are identified by (date + payee + amount).
     * The first occurrence is kept; subsequent matches are marked as duplicates.
     *
     * Cross-file duplicates compare unique rows against existing transactions
     * in the given account using the same composite key.
     *
     * @param  ValidatedRow[]  $validatedRows
     * @return array{unique_rows: ValidatedRow[], duplicate_rows: array}
     */
    public function partition(array $validatedRows, int $accountId): array
    {
        $uniqueRows = [];
        $duplicateRows = [];
        $seenKeys = [];

        foreach ($validatedRows as $index => $row) {
            $rowNumber = $index + 1;
            $key = $this->compositeKey($row);

            if (isset($seenKeys[$key])) {
                $duplicateRows[] = [
                    'row_number' => $rowNumber,
                    'reason' => sprintf(
                        'Duplicate of row %d: same date, payee, and amount.',
                        $seenKeys[$key]
                    ),
                ];

                continue;
            }

            if ($this->existsInDatabase($row, $accountId)) {
                $duplicateRows[] = [
                    'row_number' => $rowNumber,
                    'reason' => 'Matches an existing transaction in this account.',
                ];

                continue;
            }

            $seenKeys[$key] = $rowNumber;
            $uniqueRows[] = $row;
        }

        return [
            'unique_rows' => $uniqueRows,
            'duplicate_rows' => $duplicateRows,
        ];
    }

    private function compositeKey(ValidatedRow $row): string
    {
        return "{$row->date}|{$row->payee}|{$row->amount}";
    }

    private function existsInDatabase(ValidatedRow $row, int $accountId): bool
    {
        return Transaction::where('account_id', $accountId)
            ->whereDate('date', $row->date)
            ->whereIn('amount', [$row->amount, -$row->amount])
            ->whereHas('payee', fn ($q) => $q->where('name', $row->payee))
            ->exists();
    }
}
