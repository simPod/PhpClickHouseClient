<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Sql\NativeParameterParser;
use SimPod\ClickHouseClient\Sql\Type;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function array_map;
use function implode;
use function range;
use function str_repeat;

#[CoversClass(NativeParameterParser::class)]
final class NativeParameterParserTest extends TestCaseBase
{
    public function testParse(): void
    {
        self::assertEquals(
            [
                'serverIds' => Type::fromString('Array(UUID)'),
                'sensorIds' => Type::fromString('Array(Array(UUID))'),
                'recordedAt' => Type::fromString("DateTime64(6, 'UTC')"),
            ],
            NativeParameterParser::parse(
                "SELECT {serverIds: Array(UUID)}, {sensorIds:Array(Array(UUID))}, {recordedAt:DateTime64(6, 'UTC')}",
            ),
        );
    }

    #[DataProvider('provideParseIgnoredText')]
    public function testParseIgnoredText(string $sql): void
    {
        self::assertSame([], NativeParameterParser::parse($sql));
    }

    /** @phpstan-return Generator<string, array{string}> */
    public static function provideParseIgnoredText(): Generator
    {
        yield 'string literal' => ["SELECT '{context:UnknownType}'"];
        yield 'escaped quote' => [
            <<<'SQL'
            SELECT 'it\'s {context:UnknownType}'
            SQL,
        ];

        yield 'doubled quote' => ["SELECT 'it''s {context:UnknownType}'"];
        yield 'double-quoted identifier' => ['SELECT "{context:UnknownType}"'];
        yield 'doubled double quote' => ['SELECT "a""{context:UnknownType}"'];
        yield 'backtick-quoted identifier' => ['SELECT `{context:UnknownType}`'];
        yield 'escaped backtick' => ['SELECT `a\`{context:UnknownType}`'];
        yield 'unicode string literal' => ['SELECT ‘{context:UnknownType}’'];
        yield 'unicode string with em dash' => ['SELECT ‘a—{context:UnknownType}’'];
        yield 'unicode quoted identifier' => ['SELECT “{context:UnknownType}”'];
        yield 'unicode identifier with emoji' => ['SELECT “😀{context:UnknownType}”'];
        yield 'heredoc' => ['SELECT $$\'quoted\' {context:UnknownType} $$'];
        yield 'tagged heredoc' => ['SELECT $sql$\'quoted\' {context:UnknownType} $other$ $sql$'];
        yield 'dash comment' => ["SELECT 1 -- 'quoted' {context:UnknownType}\n"];
        yield 'comment at end of input' => ['SELECT 1 -- {context:UnknownType}'];
        yield 'slash comment' => ["SELECT 1 // 'quoted' {context:UnknownType}\n"];
        yield 'hash comment' => ["SELECT 1 # 'quoted' {context:UnknownType}\n"];
        yield 'shebang comment' => ["#! {context:UnknownType}\nSELECT 1"];
        yield 'block comment' => ["SELECT 1 /* 'quoted' {context:UnknownType} */"];
        yield 'nested block comment' => ['SELECT 1 /* /* nested */ {context:UnknownType} */'];
    }

    #[DataProvider('provideParseAroundIgnoredText')]
    public function testParseAroundIgnoredText(string $sql): void
    {
        self::assertEquals(['context' => Type::fromString('String')], NativeParameterParser::parse($sql));
    }

    /** @phpstan-return Generator<string, array{string}> */
    public static function provideParseAroundIgnoredText(): Generator
    {
        yield 'literal before real parameter' => ["SELECT '{context:UnknownType}', {context:String}"];
        yield 'literal after real parameter' => ["SELECT {context:String}, '{context:UnknownType}'"];
        yield 'parameter after escaped backslash' => [
            <<<'SQL'
            SELECT '\\', {context:String}
            SQL,
        ];

        yield 'parameter after line comment' => ["SELECT -- {context:UnknownType}\n{context:String}"];
        yield 'parameter after empty line comment' => ["SELECT --\n{context:String}"];
        yield 'parameter after empty block comment' => ['SELECT /**/{context:String}'];
        yield 'parameter directly after block comment' => ['SELECT /* ignored */{context:String}'];
        yield 'parameter after nested comment' => ['SELECT /* /* nested */ {context:UnknownType} */ {context:String}'];

        yield 'parameter after heredoc' => ['SELECT $sql${context:UnknownType}$sql$, {context:String}'];
        yield 'heredoc after real parameter' => ['SELECT {context:String}, $sql${context:UnknownType}$sql$'];
        yield 'parameter after empty heredoc' => ['SELECT $$$$, {context:String}'];
        yield 'parameter after unicode identifier' => ['SELECT “😀{context:UnknownType}”, {context:String}'];
        yield 'parameter after empty unicode string' => ['SELECT ‘’, {context:String}'];
        yield 'unicode mathematical minus' => ['SELECT 1 − 2, {context:String}'];
        yield 'dollars in bare identifiers' => ['SELECT foo$sql$, {context:String}, foo$sql$ FROM t'];
        yield 'many distinct dollar-bearing identifiers' => [
            'SELECT ' . implode(', ', array_map(static fn (int $i) => '$column' . $i . '$', range(0, 29_999)))
                . ', {context:String} FROM t',
        ];

        yield 'parameter after large escaped literal' => [
            "SELECT '" . str_repeat('\\x', 10_000) . "', {context:String}",
        ];

        yield 'parameter after large block comment' => [
            'SELECT /* ' . str_repeat('/', 10_000) . ' */ {context:String}',
        ];
    }
}
