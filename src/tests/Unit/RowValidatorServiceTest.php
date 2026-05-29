<?php

namespace Tests\Unit;

use App\Services\RowValidationException;
use App\Services\RowValidatorService;
use App\Services\ValidatedRow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RowValidatorServiceTest extends TestCase
{
    private RowValidatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RowValidatorService();
    }

    // -----------------------------------------------------------------------
    //  Happy path — valid rows
    // -----------------------------------------------------------------------

    #[Test]
    public function it_validates_rows_with_y_m_d_date_format(): void
    {
        $rows = [
            ['date' => '2024-01-15', 'payee' => 'Store', 'memo' => 'Groceries', 'amount' => '45.50'],
        ];

        $result = $this->service->validate($rows);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ValidatedRow::class, $result[0]);
        $this->assertSame('2024-01-15', $result[0]->date);
        $this->assertSame('Store', $result[0]->payee);
        $this->assertSame('Groceries', $result[0]->memo);
        $this->assertSame(45.50, $result[0]->amount);
        $this->assertFalse($result[0]->is_expense);
    }

    #[Test]
    #[DataProvider('dateFormatProvider')]
    public function it_accepts_various_date_formats(string $input, string $expected): void
    {
        $rows = [
            ['date' => $input, 'payee' => 'Test', 'memo' => 'x', 'amount' => '10'],
        ];

        $result = $this->service->validate($rows);
        $this->assertSame($expected, $result[0]->date);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dateFormatProvider(): array
    {
        return [
            'Y-m-d'       => ['2024-01-15', '2024-01-15'],
            'm/d/Y'       => ['01/15/2024', '2024-01-15'],
            'd/m/Y'       => ['15/01/2024', '2024-01-15'],
            'Y/m/d'       => ['2024/01/15', '2024-01-15'],
            'M d Y'       => ['Jan 15 2024', '2024-01-15'],
            'd M Y'       => ['15 Jan 2024', '2024-01-15'],
            'single-digit' => ['1/5/2024', '2024-01-05'],
        ];
    }

    #[Test]
    public function it_marks_positive_amounts_as_income(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Employer', 'memo' => 'Salary', 'amount' => '3000.00'],
        ];

        $result = $this->service->validate($rows);
        $this->assertFalse($result[0]->is_expense);
        $this->assertSame(3000.0, $result[0]->amount);
    }

    #[Test]
    public function it_marks_negative_amounts_as_expense(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'Groceries', 'amount' => '-45.50'],
        ];

        $result = $this->service->validate($rows);
        $this->assertTrue($result[0]->is_expense);
        // Amount is stored as absolute value (matching Transaction model convention)
        $this->assertSame(45.50, $result[0]->amount);
    }

    // -----------------------------------------------------------------------
    //  Zero amounts — silently skipped
    // -----------------------------------------------------------------------

    #[Test]
    public function it_skips_zero_amount_rows_silently(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'Item', 'amount' => '0.00'],
            ['date' => '2024-01-02', 'payee' => 'Market', 'memo' => 'Fruit', 'amount' => '10.00'],
        ];

        $result = $this->service->validate($rows);
        $this->assertCount(1, $result);
        $this->assertSame('Market', $result[0]->payee);
        $this->assertSame(10.0, $result[0]->amount);
    }

    #[Test]
    public function it_skips_multiple_zero_amount_rows(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'A', 'memo' => 'x', 'amount' => '0'],
            ['date' => '2024-01-02', 'payee' => 'B', 'memo' => 'x', 'amount' => '0.0'],
            ['date' => '2024-01-03', 'payee' => 'C', 'memo' => 'x', 'amount' => '10'],
        ];

        $result = $this->service->validate($rows);
        $this->assertCount(1, $result);
        $this->assertSame('C', $result[0]->payee);
    }

    // -----------------------------------------------------------------------
    //  Memo truncation
    // -----------------------------------------------------------------------

    #[Test]
    public function it_truncates_memo_to_255_characters(): void
    {
        $longMemo = str_repeat('a', 300);
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => $longMemo, 'amount' => '10'],
        ];

        $result = $this->service->validate($rows);
        $this->assertSame(255, mb_strlen($result[0]->memo));
        $this->assertStringEndsWith(str_repeat('a', 255), $result[0]->memo);
    }

    #[Test]
    public function it_does_not_truncate_memo_under_255(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'Short note', 'amount' => '10'],
        ];

        $result = $this->service->validate($rows);
        $this->assertSame('Short note', $result[0]->memo);
    }

    // -----------------------------------------------------------------------
    //  Error cases
    // -----------------------------------------------------------------------

    #[Test]
    public function it_rejects_unparseable_date(): void
    {
        $rows = [
            ['date' => 'not-a-date', 'payee' => 'Store', 'memo' => 'x', 'amount' => '10'],
        ];

        try {
            $this->service->validate($rows);
            $this->fail('Expected RowValidationException was not thrown.');
        } catch (RowValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame(1, $errors[0]['row_number']);
            $this->assertStringContainsString('Unparseable date', $errors[0]['reason']);
        }
    }

    #[Test]
    public function it_rejects_empty_date(): void
    {
        $rows = [
            ['date' => '', 'payee' => 'Store', 'memo' => 'x', 'amount' => '10'],
        ];

        try {
            $this->service->validate($rows);
            $this->fail('Expected RowValidationException was not thrown.');
        } catch (RowValidationException $e) {
            $this->assertStringContainsString('Date is empty', $e->getErrors()[0]['reason']);
        }
    }

    #[Test]
    public function it_rejects_non_numeric_amount(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'x', 'amount' => 'abc'],
        ];

        try {
            $this->service->validate($rows);
            $this->fail('Expected RowValidationException was not thrown.');
        } catch (RowValidationException $e) {
            $this->assertStringContainsString('not numeric', $e->getErrors()[0]['reason']);
        }
    }

    #[Test]
    public function it_rejects_empty_amount(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'x', 'amount' => ''],
        ];

        try {
            $this->service->validate($rows);
            $this->fail('Expected RowValidationException was not thrown.');
        } catch (RowValidationException $e) {
            $this->assertStringContainsString('Amount is empty', $e->getErrors()[0]['reason']);
        }
    }

    #[Test]
    public function it_rejects_empty_payee(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => '', 'memo' => 'x', 'amount' => '10'],
        ];

        try {
            $this->service->validate($rows);
            $this->fail('Expected RowValidationException was not thrown.');
        } catch (RowValidationException $e) {
            $this->assertStringContainsString('Payee is empty', $e->getErrors()[0]['reason']);
        }
    }

    // -----------------------------------------------------------------------
    //  Mixed valid/invalid batch
    // -----------------------------------------------------------------------

    #[Test]
    public function it_rejects_batch_when_any_row_is_invalid(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'Valid', 'amount' => '10'],
            ['date' => 'bad-date', 'payee' => 'Market', 'memo' => 'Bad', 'amount' => '20'],
            ['date' => '2024-01-03', 'payee' => 'Shop', 'memo' => 'Also valid', 'amount' => '30'],
        ];

        try {
            $this->service->validate($rows);
            $this->fail('Expected RowValidationException was not thrown.');
        } catch (RowValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame(2, $errors[0]['row_number']);
            $this->assertStringContainsString('Unparseable date', $errors[0]['reason']);
        }
    }

    #[Test]
    public function it_reports_all_errors_in_batch(): void
    {
        $rows = [
            ['date' => '',          'payee' => '',     'memo' => 'x', 'amount' => 'abc'],
            ['date' => 'bad',       'payee' => 'Shop', 'memo' => 'x', 'amount' => '20'],
            ['date' => '2024-01-03','payee' => 'Shop', 'memo' => 'x', 'amount' => '30'],
        ];

        try {
            $this->service->validate($rows);
            $this->fail('Expected RowValidationException was not thrown.');
        } catch (RowValidationException $e) {
            // Row 1 has 3 errors (date empty, payee empty, amount non-numeric)
            // but we stop at first error per row, so it reports row 1's first error
            $errors = $e->getErrors();
            // Row 1 fails on first checked field (date)
            // Row 2 fails on date
            $this->assertCount(2, $errors);
        }
    }

    // -----------------------------------------------------------------------
    //  Amount with thousand separators
    // -----------------------------------------------------------------------

    #[Test]
    public function it_handles_amount_with_thousand_separator(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'x', 'amount' => '1,234.56'],
        ];

        $result = $this->service->validate($rows);
        $this->assertSame(1234.56, $result[0]->amount);
    }

    #[Test]
    public function it_handles_negative_amount_with_thousand_separator(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'x', 'amount' => '-2,500.00'],
        ];

        $result = $this->service->validate($rows);
        $this->assertTrue($result[0]->is_expense);
        $this->assertSame(2500.0, $result[0]->amount);
    }

    // -----------------------------------------------------------------------
    //  Determinism
    // -----------------------------------------------------------------------

    #[Test]
    public function it_produces_same_output_for_same_input(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'Store', 'memo' => 'x', 'amount' => '10'],
            ['date' => '2024-01-02', 'payee' => 'Shop', 'memo' => 'y', 'amount' => '-20.50'],
        ];

        $first  = $this->service->validate($rows);
        $second = $this->service->validate($rows);

        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
        $this->assertSame($first[0]->date, $second[0]->date);
        $this->assertSame($first[0]->amount, $second[0]->amount);
        $this->assertSame($first[1]->is_expense, $second[1]->is_expense);
    }

    // -----------------------------------------------------------------------
    //  Edge cases — whitespace handling
    // -----------------------------------------------------------------------

    #[Test]
    public function it_trims_whitespace_from_fields(): void
    {
        $rows = [
            ['date' => ' 2024-01-01 ', 'payee' => '  Store  ', 'memo' => '  Note  ', 'amount' => '  10  '],
        ];

        $result = $this->service->validate($rows);
        $this->assertSame('2024-01-01', $result[0]->date);
        $this->assertSame('Store', $result[0]->payee);
        $this->assertSame('Note', $result[0]->memo);
        $this->assertSame(10.0, $result[0]->amount);
    }

    #[Test]
    public function it_rejects_payee_with_only_whitespace(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => '   ', 'memo' => 'x', 'amount' => '10'],
        ];

        $this->expectException(RowValidationException::class);
        $this->service->validate($rows);
    }

    #[Test]
    public function it_returns_empty_array_for_all_zero_amounts(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'payee' => 'A', 'memo' => 'x', 'amount' => '0'],
            ['date' => '2024-01-02', 'payee' => 'B', 'memo' => 'x', 'amount' => '0.00'],
        ];

        $result = $this->service->validate($rows);
        $this->assertSame([], $result);
    }
}
