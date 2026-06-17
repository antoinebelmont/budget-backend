<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Central coordinator that sequences the full import pipeline
 * within a single atomic database transaction.
 *
 * Pipeline: parse → validate → deduplicate → match categories → persist.
 * If ANY step fails for ANY row, the entire batch is rolled back.
 */
class ImportOrchestrator
{
    public function __construct(
        private readonly CsvParserService            $csvParser,
        private readonly DuplicateDetectionService   $duplicateDetection,
        private readonly PayeeCategoryMatcher        $payeeCategoryMatcher,
        private readonly TransactionPersistenceService $transactionPersistence,
    ) {}

    /**
     * Run the full import pipeline.
     *
     * @param  string       $content     Raw CSV file content.
     * @param  int          $accountId   Target account ID.
     * @param  User         $user        Authenticated user.
     * @param  string|null  $dateFormat  Date format from import request (overrides stored preference).
     * @return ImportResult
     */
    public function import(string $content, int $accountId, User $user, ?string $dateFormat = null): ImportResult
    {
        try {
            return DB::transaction(function () use ($content, $accountId, $user, $dateFormat) {
                $userDateFormat = $dateFormat ?? ($user->preferences['date_format'] ?? null);
                $rowValidator = new RowValidatorService($userDateFormat);

                // ------------------------------------------------------------------
                // 1. Parse
                // ------------------------------------------------------------------
                $parsedRows = $this->csvParser->parse($content);

                // ------------------------------------------------------------------
                // 2. Validate
                // ------------------------------------------------------------------
                $validatedRows = $rowValidator->validate($parsedRows);

                // ------------------------------------------------------------------
                // 3. Deduplicate
                // ------------------------------------------------------------------
                $dedupResult = $this->duplicateDetection->partition($validatedRows, $accountId);
                $uniqueRows  = $dedupResult['unique_rows'];
                $ignoredRows = $dedupResult['duplicate_rows'];

                // ------------------------------------------------------------------
                // 4. Resolve categories for each unique row
                // ------------------------------------------------------------------
                $resolvedRows = [];
                foreach ($uniqueRows as $row) {
                    $resolved = $this->payeeCategoryMatcher->resolve($row, $user);
                    $resolvedRows[] = [
                        'validated_row' => $row,
                        'payee_id'      => $resolved['payee_id'],
                        'category_id'   => $resolved['category_id'],
                    ];
                }

                // ------------------------------------------------------------------
                // 5. Persist
                // ------------------------------------------------------------------
                $createdIds = $this->transactionPersistence->persistBatch(
                    $resolvedRows,
                    $accountId,
                    $user->id,
                );

                return new ImportResult(
                    imported_count: count($createdIds),
                    ignored_rows:   $ignoredRows,
                    error_rows:     [],
                    success:        true,
                );
            });
        } catch (RowValidationException $e) {
            // Per-row validation failure — report individual errors
            return new ImportResult(
                imported_count: 0,
                ignored_rows:   [],
                error_rows:     $e->getErrors(),
                success:        false,
            );
        } catch (RuntimeException $e) {
            // Structural failure (parse error, encoding, column mismatch, etc.)
            return new ImportResult(
                imported_count: 0,
                ignored_rows:   [],
                error_rows:     [['row_number' => 1, 'reason' => $e->getMessage()]],
                success:        false,
            );
        } catch (\Throwable $e) {
            // Unexpected error — still return a structured result
            return new ImportResult(
                imported_count: 0,
                ignored_rows:   [],
                error_rows:     [['row_number' => 0, 'reason' => 'Unexpected error: ' . $e->getMessage()]],
                success:        false,
            );
        }
    }
}
