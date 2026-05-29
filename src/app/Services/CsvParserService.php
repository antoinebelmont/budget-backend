<?php

namespace App\Services;

use RuntimeException;

/**
 * Stateless service that parses and validates the structure of a CSV file.
 *
 * Responsibilities:
 * - Detect delimiter (comma / semicolon / tab) by scanning the first line.
 * - Reject non-UTF-8 encodings.
 * - Validate the header row matches exactly: date, payee, memo, amount.
 * - Reject files with extra or missing columns.
 * - Enforce maximum of 10 000 rows.
 * - Return raw row data as associative arrays keyed by header name.
 */
class CsvParserService
{
    private const EXPECTED_HEADERS = ['date', 'payee', 'memo', 'amount'];
    private const MAX_ROWS = 10000;
    private const DELIMITERS = [',', ';', "\t"];

    /**
     * Parse the raw CSV content and return an array of rows.
     *
     * Each row is an associative array with keys: date, payee, memo, amount.
     *
     * @param  string  $content  Raw file content (UTF-8 expected, BOM optional).
     * @return array<int, array<string, string>>
     *
     * @throws RuntimeException  On any structural validation failure.
     */
    public function parse(string $content): array
    {
        // Strip UTF-8 BOM if present
        $content = $this->stripBom($content);

        // ------------------------------------------------------------------
        // 1. Encoding check
        // ------------------------------------------------------------------
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new RuntimeException(
                'File encoding is not valid UTF-8. Only UTF-8 encoded CSV files are supported.'
            );
        }

        // Normalise line endings (CRLF → LF, then CR → LF)
        $content = str_replace(["\r\n", "\r"], ["\n", "\n"], $content);

        // Split into lines, preserving empty trailing lines
        $lines = explode("\n", $content);

        // Remove trailing empty line(s) that come from a final newline
        while ($lines !== [] && $lines[count($lines) - 1] === '') {
            array_pop($lines);
        }

        // ------------------------------------------------------------------
        // 2. Empty file check
        // ------------------------------------------------------------------
        if ($lines === []) {
            throw new RuntimeException('File is empty.');
        }

        // ------------------------------------------------------------------
        // 3. Delimiter auto-detection (based on first line / header)
        // ------------------------------------------------------------------
        $delimiter = $this->detectDelimiter($lines[0]);

        // ------------------------------------------------------------------
        // 4. Parse header row
        // ------------------------------------------------------------------
        $headerRow = str_getcsv($lines[0], $delimiter);
        $headerRow = array_map('trim', $headerRow);
        $headerRow = array_map('strtolower', $headerRow);

        if ($headerRow !== self::EXPECTED_HEADERS) {
            // Determine whether the problem is extra columns, missing columns, or wrong names
            $expected = implode(', ', self::EXPECTED_HEADERS);
            $actual   = implode(', ', $headerRow);

            if (count($headerRow) > count(self::EXPECTED_HEADERS)) {
                throw new RuntimeException(
                    "Header row contains extra columns. Expected exactly: {$expected}. Got: {$actual}."
                );
            }

            if (count($headerRow) < count(self::EXPECTED_HEADERS)) {
                throw new RuntimeException(
                    "Header row is missing columns. Expected exactly: {$expected}. Got: {$actual}."
                );
            }

            throw new RuntimeException(
                "Header row does not match. Expected: {$expected}. Got: {$actual}."
            );
        }

        // ------------------------------------------------------------------
        // 5. Parse data rows (skip header at index 0)
        // ------------------------------------------------------------------
        $dataLines = array_slice($lines, 1);

        // Row count check
        if (count($dataLines) > self::MAX_ROWS) {
            throw new RuntimeException(
                'File exceeds the maximum of ' . self::MAX_ROWS . ' data rows (found ' . count($dataLines) . ').'
            );
        }

        $rows = [];
        foreach ($dataLines as $lineIndex => $line) {
            $line = trim($line);

            // Skip completely empty lines within the data section
            if ($line === '') {
                continue;
            }

            $fields = str_getcsv($line, $delimiter);

            if (count($fields) !== count(self::EXPECTED_HEADERS)) {
                throw new RuntimeException(
                    'Row ' . ($lineIndex + 2) . ' has ' . count($fields) .
                    ' columns, but ' . count(self::EXPECTED_HEADERS) . ' were expected.'
                );
            }

            $rows[] = array_combine(self::EXPECTED_HEADERS, $fields);
        }

        return $rows;
    }

    /**
     * Detect the most likely CSV delimiter by counting occurrences
     * of each candidate delimiter on the first line.
     */
    private function detectDelimiter(string $firstLine): string
    {
        $bestDelimiter = ',';
        $bestCount     = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $bestCount) {
                $bestCount     = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Strip a UTF-8 Byte Order Mark (BOM) from the start of the content.
     */
    private function stripBom(string $content): string
    {
        $bom = "\xEF\xBB\xBF";
        if (str_starts_with($content, $bom)) {
            return substr($content, strlen($bom));
        }

        return $content;
    }
}
