<?php

namespace Tests\Unit;

use App\Services\CsvParserService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvParserServiceTest extends TestCase
{
    private CsvParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CsvParserService();
    }

    // -----------------------------------------------------------------------
    //  Happy path — various delimiters
    // -----------------------------------------------------------------------

    #[Test]
    public function it_parses_comma_delimited_csv(): void
    {
        $csv = "date,payee,memo,amount\n2024-01-15,Supermarket,Groceries,45.50\n2024-01-16,Pharmacy,Pills,-12.00";

        $rows = $this->parser->parse($csv);

        $this->assertCount(2, $rows);
        $this->assertSame('2024-01-15', $rows[0]['date']);
        $this->assertSame('Supermarket', $rows[0]['payee']);
        $this->assertSame('Groceries', $rows[0]['memo']);
        $this->assertSame('45.50', $rows[0]['amount']);
        $this->assertSame('2024-01-16', $rows[1]['date']);
        $this->assertSame('Pharmacy', $rows[1]['payee']);
        $this->assertSame('Pills', $rows[1]['memo']);
        $this->assertSame('-12.00', $rows[1]['amount']);
    }

    #[Test]
    public function it_parses_semicolon_delimited_csv(): void
    {
        $csv = "date;payee;memo;amount\n2024-02-10;Electric Company;Bill;-85.00";

        $rows = $this->parser->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('2024-02-10', $rows[0]['date']);
        $this->assertSame('Electric Company', $rows[0]['payee']);
        $this->assertSame('Bill', $rows[0]['memo']);
        $this->assertSame('-85.00', $rows[0]['amount']);
    }

    #[Test]
    public function it_parses_tab_delimited_csv(): void
    {
        $csv = "date\tpayee\tmemo\tamount\n2024-03-05\tGas Station\tFuel\t-40.00";

        $rows = $this->parser->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('2024-03-05', $rows[0]['date']);
        $this->assertSame('Gas Station', $rows[0]['payee']);
        $this->assertSame('Fuel', $rows[0]['memo']);
        $this->assertSame('-40.00', $rows[0]['amount']);
    }

    // -----------------------------------------------------------------------
    //  Edge cases — content variations
    // -----------------------------------------------------------------------

    #[Test]
    public function it_strips_utf8_bom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $csv = $bom . "date,payee,memo,amount\n2024-04-01,Rent,Apartment,-1000.00";

        $rows = $this->parser->parse($csv);
        $this->assertCount(1, $rows);
        $this->assertSame('Rent', $rows[0]['payee']);
    }

    #[Test]
    public function it_handles_crlf_line_endings(): void
    {
        $csv = "date,payee,memo,amount\r\n2024-05-01,Employer,Salary,3000.00\r\n2024-05-02,Freelance,Invoice,500.00";

        $rows = $this->parser->parse($csv);
        $this->assertCount(2, $rows);
    }

    #[Test]
    public function it_skips_empty_lines_within_data(): void
    {
        $csv = "date,payee,memo,amount\n2024-06-01,Shop,Item,10.00\n\n\n2024-06-02,Market,Fruit,5.00";

        $rows = $this->parser->parse($csv);
        $this->assertCount(2, $rows);
    }

    #[Test]
    public function it_returns_empty_array_when_only_header(): void
    {
        $csv = "date,payee,memo,amount";

        $rows = $this->parser->parse($csv);
        $this->assertSame([], $rows);
    }

    #[Test]
    public function it_returns_empty_array_when_header_with_trailing_newline(): void
    {
        $csv = "date,payee,memo,amount\n";

        $rows = $this->parser->parse($csv);
        $this->assertSame([], $rows);
    }

    // -----------------------------------------------------------------------
    //  Error cases — file structure
    // -----------------------------------------------------------------------

    #[Test]
    public function it_rejects_empty_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty');
        $this->parser->parse('');
    }

    #[Test]
    public function it_rejects_non_utf8_encoding(): void
    {
        // ISO-8859-1 string with an en-dash (0x96) — invalid UTF-8
        $csv = "date,payee,memo,amount\n" . "\x96" . "test,payee,-,1";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('encoding');
        $this->parser->parse($csv);
    }

    #[Test]
    public function it_rejects_wrong_header_names(): void
    {
        $csv = "date,vendor,note,value\n2024-01-01,Store,Item,10";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Header');
        $this->parser->parse($csv);
    }

    #[Test]
    public function it_rejects_extra_columns(): void
    {
        $csv = "date,payee,memo,amount,category\n2024-01-01,Store,Item,10,Food";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('extra columns');
        $this->parser->parse($csv);
    }

    #[Test]
    public function it_rejects_missing_columns(): void
    {
        $csv = "date,payee,amount\n2024-01-01,Store,10";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing columns');
        $this->parser->parse($csv);
    }

    #[Test]
    public function it_rejects_data_rows_with_wrong_column_count(): void
    {
        $csv = "date,payee,memo,amount\n2024-01-01,Store,Item,10,ExtraColumn";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Row 2');
        $this->parser->parse($csv);
    }

    #[Test]
    public function it_rejects_files_exceeding_max_row_limit(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10001; $i++) {
            $rows[] = "2024-01-01,Payee{$i},Memo{$i},10.00";
        }

        $csv = "date,payee,memo,amount\n" . implode("\n", $rows);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('10000');
        $this->parser->parse($csv);
    }

    #[Test]
    public function it_accepts_exactly_10000_rows(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10000; $i++) {
            $rows[] = "2024-01-01,Payee{$i},Memo{$i},10.00";
        }

        $csv = "date,payee,memo,amount\n" . implode("\n", $rows);

        $result = $this->parser->parse($csv);
        $this->assertCount(10000, $result);
    }

    // -----------------------------------------------------------------------
    //  Determinism — statelessness
    // -----------------------------------------------------------------------

    #[Test]
    public function it_produces_same_output_for_same_input(): void
    {
        $csv = "date,payee,memo,amount\n2024-01-01,A,B,1\n2024-01-02,C,D,2";

        $first  = $this->parser->parse($csv);
        $second = $this->parser->parse($csv);

        $this->assertSame($first, $second);
    }

    // -----------------------------------------------------------------------
    //  Delimiter detection edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function it_detects_delimiter_by_highest_frequency(): void
    {
        // 2 commas, 5 semicolons → semicolon wins
        $csv = "date;payee;memo;amount\n2024-01-01;Store;Item;10";

        $rows = $this->parser->parse($csv);
        $this->assertCount(1, $rows);
        $this->assertSame('Store', $rows[0]['payee']);
    }
}
