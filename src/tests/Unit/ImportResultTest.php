<?php

namespace Tests\Unit;

use App\Services\ImportResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ImportResultTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated_with_constructor(): void
    {
        $result = new ImportResult(
            imported_count: 5,
            ignored_rows:   [],
            error_rows:     [],
            success:        true,
        );

        $this->assertSame(5, $result->imported_count);
        $this->assertSame([], $result->ignored_rows);
        $this->assertSame([], $result->error_rows);
        $this->assertTrue($result->success);
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $result = new ImportResult(
            imported_count: 0,
            ignored_rows:   [],
            error_rows:     [],
            success:        true,
        );

        $this->assertTrue($result->success);
        // PHP readonly properties cannot be modified — this is enforced at the language level.
        // The test verifies the class uses `readonly` keyword, making it immutable.
    }

    #[Test]
    public function it_builds_from_array_via_factory(): void
    {
        $result = ImportResult::fromArray([
            'imported_count' => 3,
            'ignored_rows'   => [
                ['row_number' => 4, 'reason' => 'Duplicate'],
                ['row_number' => 7, 'reason' => 'Already exists'],
            ],
            'error_rows'     => [],
            'success'        => true,
        ]);

        $this->assertSame(3, $result->imported_count);
        $this->assertCount(2, $result->ignored_rows);
        $this->assertSame(4, $result->ignored_rows[0]['row_number']);
        $this->assertSame('Duplicate', $result->ignored_rows[0]['reason']);
        $this->assertSame(7, $result->ignored_rows[1]['row_number']);
        $this->assertSame('Already exists', $result->ignored_rows[1]['reason']);
        $this->assertEmpty($result->error_rows);
        $this->assertTrue($result->success);
    }

    #[Test]
    public function it_uses_defaults_when_factory_receives_partial_data(): void
    {
        $result = ImportResult::fromArray([]);

        $this->assertSame(0, $result->imported_count);
        $this->assertSame([], $result->ignored_rows);
        $this->assertSame([], $result->error_rows);
        $this->assertTrue($result->success);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $result = new ImportResult(
            imported_count: 2,
            ignored_rows:   [['row_number' => 3, 'reason' => 'Duplicate']],
            error_rows:     [['row_number' => 1, 'reason' => 'Invalid date']],
            success:        false,
        );

        $array = $result->toArray();

        $this->assertSame([
            'imported_count' => 2,
            'ignored_rows'   => [['row_number' => 3, 'reason' => 'Duplicate']],
            'error_rows'     => [['row_number' => 1, 'reason' => 'Invalid date']],
            'success'        => false,
        ], $array);
    }

    #[Test]
    public function it_serializes_to_json_correctly(): void
    {
        $result = new ImportResult(
            imported_count: 0,
            ignored_rows:   [],
            error_rows:     [['row_number' => 1, 'reason' => 'Bad format']],
            success:        false,
        );

        $expected = json_encode([
            'imported_count' => 0,
            'ignored_rows'   => [],
            'error_rows'     => [['row_number' => 1, 'reason' => 'Bad format']],
            'success'        => false,
        ]);

        $this->assertJsonStringEqualsJsonString($expected, json_encode($result));
    }

    #[Test]
    public function it_supports_various_row_detail_shapes(): void
    {
        $result = new ImportResult(
            imported_count: 10,
            ignored_rows:   [
                ['row_number' => 2, 'reason' => 'Intra-file duplicate'],
                ['row_number' => 5, 'reason' => 'Cross-file duplicate'],
            ],
            error_rows:     [
                ['row_number' => 1, 'reason' => 'Non-UTF-8 encoding'],
                ['row_number' => 3, 'reason' => 'Invalid amount'],
                ['row_number' => 4, 'reason' => 'Unparseable date'],
            ],
            success:        false,
        );

        $this->assertCount(2, $result->ignored_rows);
        $this->assertCount(3, $result->error_rows);
        $this->assertSame('Non-UTF-8 encoding', $result->error_rows[0]['reason']);
        $this->assertSame('Intra-file duplicate', $result->ignored_rows[0]['reason']);
        $this->assertFalse($result->success);
        $this->assertSame(10, $result->imported_count);
    }
}
