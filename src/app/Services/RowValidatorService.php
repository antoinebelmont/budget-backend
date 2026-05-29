<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Stateless service that validates each parsed CSV row.
 *
 * Responsibilities:
 * - Parse dates against multiple known formats.
 * - Parse amounts as numeric; determine is_expense from sign.
 * - Silently skip zero-amount rows.
 * - Truncate memo to 255 characters.
 * - Return validated DTOs; reject the entire batch on any invalid row.
 */
class RowValidatorService
{
    /**
     * Date formats accepted, in priority order.
     */
    private const DATE_FORMATS = [
        'Y-m-d',
        'm/d/Y',
        'd/m/Y',
        'Y/m/d',
        'M d Y',
        'd M Y',
    ];

    private const MAX_MEMO_LENGTH = 255;

    /**
     * Validate an array of raw parsed rows.
     *
     * Each input row must have keys: date, payee, memo, amount.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return ValidatedRow[]
     *
     * @throws RowValidationException  If any row fails validation.
     */
    public function validate(array $rows): array
    {
        $errors = [];
        $valid  = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1; // 1-based for user-facing messages

            try {
                // --- Date ---
                $date = $this->parseDate($row['date'] ?? '', $rowNumber, $errors);

                // --- Amount ---
                $amount = $this->parseAmount($row['amount'] ?? '', $rowNumber, $errors);

                // Silent skip for zero amounts — no error, no row returned
                if ($amount === 0.0) {
                    continue;
                }

                // --- Memo ---
                $memo = $this->normalizeMemo($row['memo'] ?? '');

                // --- Payee ---
                $payee = $this->normalizePayee($row['payee'] ?? '', $rowNumber, $errors);

                $valid[] = new ValidatedRow(
                    date:       $date,
                    payee:      $payee,
                    memo:       $memo,
                    amount:     abs($amount),
                    is_expense: $amount < 0,
                );
            } catch (RowValidationException $e) {
                // Exception was already recorded in $errors; continue gathering
            }
        }

        if ($errors !== []) {
            throw new RowValidationException($errors);
        }

        return $valid;
    }

    /**
     * Parse a date string against the accepted formats.
     *
     * @throws RowValidationException  If the date cannot be parsed.
     */
    private function parseDate(string $value, int $rowNumber, array &$errors): string
    {
        $value = trim($value);

        if ($value === '') {
            $errors[] = ['row_number' => $rowNumber, 'reason' => 'Date is empty.'];
            throw new RowValidationException([]);
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed === false) {
                    continue;
                }

                // Round-trip check with zero-padding normalisation.
                // This rejects false matches where PHP normalises overflow
                // values (e.g. month 15 → 1 year + 3 months) without
                // failing on legitimate single-digit month/day inputs.
                $normalise = fn (string $s): string => preg_replace('/\b0+(\d)/', '$1', $s);
                if ($normalise($parsed->format($format)) === $normalise($value)) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Exception) {
                continue;
            }
        }

        $errors[] = ['row_number' => $rowNumber, 'reason' => "Unparseable date: \"{$value}\"."];
        throw new RowValidationException([]);
    }

    /**
     * Parse a numeric amount string. Strips non-numeric characters
     * except leading minus, decimal point, and digits.
     *
     * @throws RowValidationException  If the amount is not numeric.
     */
    private function parseAmount(string $value, int $rowNumber, array &$errors): float
    {
        $value = trim($value);

        if ($value === '') {
            $errors[] = ['row_number' => $rowNumber, 'reason' => 'Amount is empty.'];
            throw new RowValidationException([]);
        }

        // Remove thousand-separator commas (e.g. "1,234.56" → "1234.56")
        $sanitized = str_replace(',', '', $value);

        if (!is_numeric($sanitized)) {
            $errors[] = ['row_number' => $rowNumber, 'reason' => "Amount is not numeric: \"{$value}\"."];
            throw new RowValidationException([]);
        }

        return (float) $sanitized;
    }

    /**
     * Normalize the payee field: trim whitespace, ensure non-empty.
     *
     * @throws RowValidationException  If the payee is empty after trimming.
     */
    private function normalizePayee(string $value, int $rowNumber, array &$errors): string
    {
        $value = trim($value);

        if ($value === '') {
            $errors[] = ['row_number' => $rowNumber, 'reason' => 'Payee is empty.'];
            throw new RowValidationException([]);
        }

        return $value;
    }

    /**
     * Normalize the memo field: trim whitespace, truncate to max length.
     */
    private function normalizeMemo(string $value): string
    {
        $value = trim($value);

        if (mb_strlen($value) > self::MAX_MEMO_LENGTH) {
            return mb_substr($value, 0, self::MAX_MEMO_LENGTH);
        }

        return $value;
    }
}
