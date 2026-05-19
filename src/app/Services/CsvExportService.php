<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CsvExportService
{
    private const CSV_HEADERS_WITH_ACCOUNT = [
        'Date',
        'Account',
        'Category',
        'Payee',
        'Memo',
        'Amount',
        'Cleared'
    ];

    private const CSV_HEADERS_WITHOUT_ACCOUNT = [
        'Date',
        'Category',
        'Payee',
        'Memo',
        'Amount',
        'Cleared'
    ];

    public function generateCsv(Collection $transactions, bool $includeAccount = true): string
    {
        $csvLines = [];
        $csvLines[] = $this->generateHeaderRow($includeAccount);

        foreach ($transactions as $transaction) {
            $csvLines[] = $this->generateDataRow($transaction, $includeAccount);
        }

        return implode("\n", $csvLines);
    }

    private function generateHeaderRow(bool $includeAccount): string
    {
        $headers = $includeAccount ? self::CSV_HEADERS_WITH_ACCOUNT : self::CSV_HEADERS_WITHOUT_ACCOUNT;
        return implode(',', array_map(
            [$this, 'escapeField'],
            $headers
        ));
    }

    private function generateDataRow($transaction, bool $includeAccount): string
    {
        if ($includeAccount) {
            $row = [
                $this->formatDate($transaction->date),
                $this->getRelationName($transaction->account),
                $this->getRelationName($transaction->category),
                $this->getRelationName($transaction->payee),
                $this->getFieldValue($transaction->memo),
                $this->formatAmount($transaction->amount),
                $this->getFieldValue($transaction->cleared),
            ];
        } else {
            $row = [
                $this->formatDate($transaction->date),
                $this->getRelationName($transaction->category),
                $this->getRelationName($transaction->payee),
                $this->getFieldValue($transaction->memo),
                $this->formatAmount($transaction->amount),
                $this->getFieldValue($transaction->cleared),
            ];
        }

        return implode(',', array_map(
            [$this, 'escapeField'],
            $row
        ));
    }

    private function formatDate($date): string
    {
        if (is_null($date)) {
            return '';
        }

        if ($date instanceof \Carbon\Carbon) {
            return $date->format('Y-m-d');
        }

        return \Carbon\Carbon::parse($date)->format('Y-m-d');
    }

    private function formatAmount($amount): string
    {
        if (is_null($amount)) {
            return '0.00';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function getRelationName($relation): string
    {
        if (is_null($relation)) {
            return '';
        }

        return $relation->name ?? '';
    }

    private function getFieldValue($value): string
    {
        if (is_null($value)) {
            return '';
        }

        return (string) $value;
    }

    private function escapeField($field): string
    {
        $field = (string) $field;

        if (strpos($field, ',') !== false || 
            strpos($field, '"') !== false || 
            strpos($field, "\n") !== false ||
            strpos($field, "\r") !== false) {
            $field = '"' . str_replace('"', '""', $field) . '"';
        }

        return $field;
    }
}