<?php

namespace App\Services;

/**
 * Read-only DTO representing a single validated CSV row
 * ready for deduplication and persistence.
 */
readonly class ValidatedRow
{
    public function __construct(
        public string $date,
        public string $payee,
        public string $memo,
        public float  $amount,
        public bool   $is_expense,
    ) {}
}
