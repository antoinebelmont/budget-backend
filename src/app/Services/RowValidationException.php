<?php

namespace App\Services;

use RuntimeException;

/**
 * Exception thrown when one or more rows fail validation.
 * Carries structured per-row error details for the orchestrator
 * to build an ImportResult.
 */
class RowValidationException extends RuntimeException
{
    /**
     * @param array<int, array{row_number: int, reason: string}> $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {
        $count = count($errors);
        parent::__construct("{$count} row(s) failed validation.");
    }

    /**
     * @return array<int, array{row_number: int, reason: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
