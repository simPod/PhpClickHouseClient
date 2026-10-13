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
use SimPod\ClickHouseClient\Client\Native\Block;
use SimPod\ClickHouseClient\Client\Native\BlockCodec;
use SimPod\ClickHouseClient\Client\Native\Column;
use SimPod\ClickHouseClient\Client\Native\TypeCodec;

use function array_fill;
use function fopen;
use function fwrite;
use function hex2bin;
use function rewind;
use function stream_get_contents;

#[CoversClass(BlockCodec::class)]
#[UsesClass(BinaryStream::class)]
#[UsesClass(Block::class)]
#[UsesClass(Column::class)]
#[UsesClass(TypeCodec::class)]
final class BlockCodecTest extends TestCase
{
    #[DataProvider('provideWireBlocks')]
    public function testWireBlocks(int $revision, string $hex, Block $expected): void
    {
        $bytes = hex2bin($hex);
        self::assertIsString($bytes);
        $stream = $this->stream($bytes . "\xa5");
        $codec  = new BlockCodec($revision);

        $actual = $codec->read($stream);
        self::assertEquals($expected->columns, $actual->columns);
        self::assertSame($expected->rows, $actual->rows);
        self::assertSame("\xa5", $stream->read(1));

        $output = fopen('php://memory', 'w+b');
        self::assertIsResource($output);
        $writer = new BinaryStream($output);
        $codec->write($writer, $expected);
        rewind($output);

        self::assertSame($bytes, stream_get_contents($output));
    }

    /** @phpstan-return Generator<string, array{int, string, Block}> */
    public static function provideWireBlocks(): Generator
    {
        yield 'Native empty sentinel' => [54459, '010002ffffffff000000', new Block()];
        yield 'Native schema header' => [
            54459,
            '010002ffffffff00010001310555496e743800',
            new Block([new Column('1', 'UInt8')]),
        ];

        yield 'Native columnar result' => [
            54459,
            '010002ffffffff000202016e0655496e743136000100ffff017306537472696e67000161020062',
            new Block([new Column('n', 'UInt16'), new Column('s', 'String')], [[1, 'a'], [65535, "\x00b"]]),
        ];

        yield 'Before custom serialization revision' => [
            54453,
            '010002ffffffff00010101310555496e743801',
            new Block([new Column('1', 'UInt8')], [[1]]),
        ];

        yield 'Native format revision zero' => [
            0,
            '010101310555496e743801',
            new Block([new Column('1', 'UInt8')], [[1]]),
        ];
    }

    #[DataProvider('provideMalformedBlocks')]
    public function testMalformedBlocks(string $hex): void
    {
        $bytes = hex2bin($hex);
        self::assertIsString($bytes);
        self::expectException(RuntimeException::class);
        new BlockCodec(54459)->read($this->stream($bytes));
    }

    /** @phpstan-return Generator<string, array{string}> */
    public static function provideMalformedBlocks(): Generator
    {
        yield 'Unknown BlockInfo field' => ['03'];
        yield 'Duplicate BlockInfo field' => ['01000100'];
        yield 'Invalid BlockInfo overflow flag' => ['0102'];
        yield 'Excessive columns' => ['00810800'];
        yield 'Excessive rows' => ['0001c1843d'];
        yield 'Individually valid dimensions exceed allocation budget' => ['008008c0843d'];
        yield 'Rows without columns' => ['000001'];
        yield 'Custom column serialization' => ['00010101310555496e743801'];
        yield 'Unsupported type in header' => ['0001000178045555494400'];
        yield 'Truncated column data' => ['00010101310555496e743800'];
    }

    #[DataProvider('provideInvalidBlocks')]
    public function testInvalidBlocks(Block $block): void
    {
        self::expectException(RuntimeException::class);
        new BlockCodec(54459)->write($this->stream(''), $block);
    }

    /** @phpstan-return Generator<string, array{Block}> */
    public static function provideInvalidBlocks(): Generator
    {
        yield 'Missing row value' => [new Block([new Column('n', 'UInt8')], [[]])];
        yield 'Extra row value' => [new Block([new Column('n', 'UInt8')], [[1, 2]])];
        yield 'Rows without columns' => [new Block([], [[1]])];
        yield 'Too many columns' => [new Block(array_fill(0, 1025, new Column('n', 'UInt8')))];
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
