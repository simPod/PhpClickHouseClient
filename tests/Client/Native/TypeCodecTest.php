<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Native;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimPod\ClickHouseClient\Client\Native\BinaryStream;
use SimPod\ClickHouseClient\Client\Native\TypeCodec;

use function count;
use function fopen;
use function fwrite;
use function hex2bin;
use function rewind;
use function str_repeat;
use function stream_get_contents;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(TypeCodec::class)]
#[UsesClass(BinaryStream::class)]
final class TypeCodecTest extends TestCase
{
    /** @param list<mixed> $values */
    #[DataProvider('provideWireValues')]
    public function testWireValues(string $type, string $hex, array $values): void
    {
        $bytes = hex2bin($hex);
        self::assertIsString($bytes);
        $stream = $this->stream($bytes . "\xa5");
        $codec  = new TypeCodec();

        self::assertSame($values, $codec->read($stream, $type, count($values)));
        self::assertSame("\xa5", $stream->read(1));

        $output = fopen('php://memory', 'w+b');
        self::assertIsResource($output);
        $writer = new BinaryStream($output);
        $codec->write($writer, $type, $values);
        rewind($output);

        self::assertSame($bytes, stream_get_contents($output));
    }

    /** @phpstan-return Generator<string, array{string, string, list<mixed>}> */
    public static function provideWireValues(): Generator
    {
        yield 'UInt8' => ['UInt8', '00ff', [0, 255]];
        yield 'UInt16' => ['UInt16', '00003412ffff', [0, 4660, 65535]];
        yield 'UInt32' => ['UInt32', '0000000078563412ffffffff', [0, 305419896, 4294967295]];
        yield 'UInt64' => [
            'UInt64',
            '0000000000000000ffffffffffffff7f0000000000000080ffffffffffffffffd20a1feb8ca954ab',
            [0, PHP_INT_MAX, '9223372036854775808', '18446744073709551615', '12345678901234567890'],
        ];

        yield 'Int8' => ['Int8', '80ff007f', [-128, -1, 0, 127]];
        yield 'Int16' => ['Int16', '0080ffff0000ff7f', [-32768, -1, 0, 32767]];
        yield 'Int32' => ['Int32', '00000080ffffffff00000000ffffff7f', [-2147483648, -1, 0, 2147483647]];
        yield 'Int64' => [
            'Int64',
            '0000000000000080ffffffffffffffff0000000000000000ffffffffffffff7f',
            [PHP_INT_MIN, -1, 0, PHP_INT_MAX],
        ];

        yield 'Float32' => ['Float32', '0000c03f000010c0', [1.5, -2.25]];
        yield 'Float64' => ['Float64', '000000000000f83f00000000000002c0', [1.5, -2.25]];
        yield 'Bool' => ['Bool', '010001', [true, false, true]];
        yield 'ProfileEvents enum' => ["Enum8('Increment' = 1, 'Gauge' = 2)", '0102', [1, 2]];
        yield 'Signed enum values' => ["Enum16('negative' = -1, 'large' = 30000)", 'ffff3075', [-1, 30000]];
        yield 'Nullable enum with non-zero default' => ["Nullable(Enum8('ready' = 7))", '01000707', [null, 7]];
        yield 'Escaped enum labels' => ["Enum8('a\\'b' = 1, 'a,b' = 2)", '0102', [1, 2]];
        yield 'String binary values' => ['String', '000361006203e29883', ['', "a\x00b", '☃']];
        yield 'FixedString preserves null bytes' => ['FixedString(3)', '610000780079', ["a\x00\x00", "x\x00y"]];
        yield 'Date day count' => ['Date', '0100ffff', [1, 65535]];
        yield 'Date32 signed day count' => ['Date32', '219cffffffffffff', [-25567, -1]];
        yield 'DateTime seconds' => ['DateTime', '685bf465ffffffff', [1710513000, 4294967295]];
        yield 'DateTime timezone metadata' => ['DateTime(\'Europe/Prague\')', '685bf465', [1710513000]];
        yield 'DateTime64 ticks' => ['DateTime64(3)', '83511a0d8d010000ffffffffffffffff', [1705321845123, -1]];
        yield 'DateTime64 timezone metadata' => ['DateTime64(9, \'UTC\')', 'ffffffffffffffff', [-1]];
        yield 'Nullable values and defaults' => ['Nullable(Int16)', '00010000ffff000000000700', [-1, null, 0, 7]];
        yield 'Array offsets and binary strings' => [
            'Array(String)',
            '020000000000000002000000000000000300000000000000016100020078',
            [['a', ''], [], ["\x00x"]],
        ];

        yield 'Nested array offsets' => [
            'Array(Array(UInt8))',
            '02000000000000000300000000000000020000000000000002000000000000000300000000000000010203',
            [[[1, 2], []], [[3]]],
        ];

        yield 'Nullable array elements' => [
            'Array(Nullable(UInt8))',
            '0300000000000000000100010002',
            [[1, null, 2]],
        ];

        yield 'Tuple columns' => [
            'Tuple(UInt8, Array(DateTime64(3, \'UTC\')))',
            '010201000000000000000100000000000000ffffffffffffffff',
            [[1, [-1]], [2, []]],
        ];

        yield 'Nullable tuple defaults' => [
            'Nullable(Tuple(UInt8, String))',
            '01000007000178',
            [null, [7, 'x']],
        ];

        yield 'Empty tuple' => ['Tuple()', '', [[], []]];
    }

    public function testFixedStringPadsShortValues(): void
    {
        $resource = fopen('php://memory', 'w+b');
        self::assertIsResource($resource);
        $stream = new BinaryStream($resource);
        new TypeCodec()->write($stream, 'FixedString(4)', ['a', '']);
        rewind($resource);

        self::assertSame("a\x00\x00\x00\x00\x00\x00\x00", stream_get_contents($resource));
    }

    public function testBoolAcceptsNonCanonicalTrue(): void
    {
        self::assertSame([true], new TypeCodec()->read($this->stream("\xff"), 'Bool', 1));
    }

    public function testNullableMasksNonDefaultPayload(): void
    {
        self::assertSame([null, 0], new TypeCodec()->read($this->stream("\x01\x00\xff\x00"), 'Nullable(UInt8)', 2));
    }

    public function testColumnValuesMustBeAList(): void
    {
        self::expectException(RuntimeException::class);
        new TypeCodec()->write($this->stream(''), 'UInt8', ['key' => 1]);
    }

    public function testNestedContainersShareAllocationBudget(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('allocation budget');
        // Empty tuples have no payload bytes but still allocate a container for each row.
        new TypeCodec()->read($this->stream(''), 'Tuple(Tuple(), Tuple())', 200000);
    }

    /** @param list<mixed> $values */
    #[DataProvider('provideInvalidValues')]
    public function testInvalidValues(string $type, array $values): void
    {
        self::expectException(RuntimeException::class);
        new TypeCodec()->write($this->stream(''), $type, $values);
    }

    /** @phpstan-return Generator<string, array{string, list<mixed>}> */
    public static function provideInvalidValues(): Generator
    {
        yield 'Unsigned negative' => ['UInt8', [-1]];
        yield 'Unsigned overflow' => ['UInt16', [65536]];
        yield 'Signed underflow' => ['Int32', [-2147483649]];
        yield 'Signed overflow' => ['Int8', [128]];
        yield 'Unsigned 64 negative' => ['UInt64', [-1]];
        yield 'Unsigned 64 decimal overflow' => ['UInt64', ['18446744073709551616']];
        yield 'Unsigned 64 negative string' => ['UInt64', ['-1']];
        yield 'Unsigned 64 noncanonical decimal' => ['UInt64', ['01']];
        yield 'Undeclared enum index' => ["Enum8('ready' = 7)", [0]];
        yield 'Unsigned 64 scientific notation' => ['UInt64', ['1e3']];
        yield 'Integer precision loss' => ['Int64', [1.0]];
        yield 'Integer numeric string' => ['UInt32', ['1']];
        yield 'Float invalid type' => ['Float64', ['1.5']];
        yield 'Float32 overflow' => ['Float32', [1.0e100]];
        yield 'Bool integer coercion' => ['Bool', [1]];
        yield 'String null coercion' => ['String', [null]];
        yield 'FixedString truncation' => ['FixedString(2)', ['abc']];
        yield 'Date range' => ['Date', [-1]];
        yield 'DateTime range' => ['DateTime(\'UTC\')', [4294967296]];
        yield 'Array nonlist' => ['Array(UInt8)', [['key' => 1]]];
        yield 'Array invalid inner value' => ['Array(UInt8)', [[256]]];
        yield 'Tuple width mismatch' => ['Tuple(UInt8, String)', [[1]]];
        yield 'Nullable invalid present value' => ['Nullable(Int8)', [128]];
    }

    #[DataProvider('provideInvalidTypes')]
    public function testInvalidTypesEvenInEmptyColumns(string $type): void
    {
        self::expectException(RuntimeException::class);
        new TypeCodec()->read($this->stream(''), $type, 0);
    }

    /** @phpstan-return Generator<string, array{string}> */
    public static function provideInvalidTypes(): Generator
    {
        yield 'Unsupported leaf' => ['Decimal(10, 2)'];
        yield 'Unsupported nested leaf' => ['Array(LowCardinality(String))'];
        yield 'Empty nullable' => ['Nullable()'];
        yield 'Empty array' => ['Array()'];
        yield 'Unexpected scalar parameters' => ['UInt8()'];
        yield 'Malformed tuple' => ['Tuple(UInt8,,String)'];
        yield 'Unbalanced nesting' => ['Array(Tuple(UInt8, String)'];
        yield 'Named tuple unsupported' => ['Tuple(id UInt8)'];
        yield 'FixedString zero' => ['FixedString(0)'];
        yield 'FixedString unreasonable size' => ['FixedString(67108865)'];
        yield 'DateTime empty parameter' => ['DateTime()'];
        yield 'DateTime unquoted timezone' => ['DateTime(UTC)'];
        yield 'DateTime64 missing scale' => ['DateTime64'];
        yield 'DateTime64 invalid scale' => ['DateTime64(10)'];
        yield 'DateTime64 malformed timezone' => ["DateTime64(3, 'UTC)"];
        yield 'Nesting limit' => [str_repeat('Array(', 33) . 'UInt8' . str_repeat(')', 33)];
        yield 'Declaration size limit' => [str_repeat('x', 4097)];
    }

    #[DataProvider('provideMalformedData')]
    public function testMalformedData(string $type, string $hex, int $rows): void
    {
        $bytes = hex2bin($hex);
        self::assertIsString($bytes);
        self::expectException(RuntimeException::class);
        new TypeCodec()->read($this->stream($bytes), $type, $rows);
    }

    /** @phpstan-return Generator<string, array{string, string, int}> */
    public static function provideMalformedData(): Generator
    {
        yield 'Invalid null map' => ['Nullable(UInt8)', '0200', 1];
        yield 'Descending array offsets' => ['Array(UInt8)', '02000000000000000100000000000000', 2];
        yield 'Array offset above element bound' => ['Array(UInt8)', '41420f0000000000', 1];
        yield 'Array offset above PHP range' => ['Array(UInt8)', 'ffffffffffffffff', 1];
        yield 'Nested array offset bound' => ['Array(Array(UInt8))', '010000000000000041420f0000000000', 1];
        yield 'Truncated fixed width data' => ['UInt32', '010000', 1];
        yield 'Truncated string data' => ['String', '0261', 1];
        yield 'String above byte bound' => ['String', '81808020', 1];
        yield 'Fixed width column above byte bound' => ['FixedString(67108864)', '', 2];
        yield 'Negative row count' => ['UInt8', '', -1];
        yield 'Excessive row count' => ['UInt8', '', 1000001];
    }

    private function stream(string $bytes): BinaryStream
    {
        $resource = fopen('php://memory', 'w+b');
        self::assertIsResource($resource);
        fwrite($resource, $bytes);
        rewind($resource);

        return new BinaryStream($resource);
    }
}
