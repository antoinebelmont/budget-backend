<?php

namespace App\Services;

use JsonSerializable;

/**
 * Read-only value object holding the structured outcome of a CSV import operation.
 *
 * Properties:
 * - imported_count: number of transactions successfully persisted
 * - ignored_rows:   array of ["row_number" => int, "reason" => string] for duplicates
 * - error_rows:     array of ["row_number" => int, "reason" => string] for parse/validation failures
 * - success:        whether the entire batch was processed without errors
 */
readonly class ImportResult implements JsonSerializable
{
    public function __construct(
        public int   $imported_count,
        public array $ignored_rows,
        public array $error_rows,
        public bool  $success,
    ) {}

    /**
     * Build an instance from an associative array.
     *
     * @param array{imported_count?: int, ignored_rows?: array, error_rows?: array, success?: bool} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            imported_count: $data['imported_count'] ?? 0,
            ignored_rows:   $data['ignored_rows'] ?? [],
            error_rows:     $data['error_rows'] ?? [],
            success:        $data['success'] ?? true,
        );
    }

    /**
     * Return the object data as an associative array.
     *
     * @return array{imported_count: int, ignored_rows: array, error_rows: array, success: bool}
     */
    public function toArray(): array
    {
        return [
            'imported_count' => $this->imported_count,
            'ignored_rows'   => $this->ignored_rows,
            'error_rows'     => $this->error_rows,
            'success'        => $this->success,
        ];
    }

    /**
     * JSON serialization — delegates to toArray().
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
