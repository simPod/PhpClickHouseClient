<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Http;

use DateTimeImmutable;
use Generator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Client\Http\RequestSettings;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Settings\ArraySettingsProvider;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Sql\NativeParameterParser;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function array_map;
use function implode;
use function range;
use function sprintf;
use function str_repeat;

#[CoversClass(RequestFactory::class)]
#[CoversClass(NativeParameterParser::class)]
final class RequestFactoryTest extends TestCaseBase
{
    #[DataProvider('providerPrepareRequest')]
    public function testPrepareRequest(string $uri, string $expectedUri): void
    {
        $psr17Factory   = new Psr17Factory();
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $uri,
        );

        $request = $requestFactory->prepareSqlRequest(
            'SELECT 1',
            new RequestSettings(
                new ArraySettingsProvider(['max_block_size' => 1]),
                new ArraySettingsProvider(['database' => 'database']),
            ),
            new RequestOptions(
                [],
            ),
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            $expectedUri,
            $request->getUri()->__toString(),
        );
        self::assertStringContainsString('SELECT 1', $request->getBody()->__toString());
    }

    /** @return Generator<string, array{string, string}> */
    public static function providerPrepareRequest(): Generator
    {
        yield 'uri with query' => [
            'http://localhost:8123?format=JSON',
            'http://localhost:8123?format=JSON&database=database&max_block_size=1',
        ];

        yield 'uri without query' => [
            'http://localhost:8123',
            'http://localhost:8123?database=database&max_block_size=1',
        ];

        yield 'empty uri' => [
            '',
            '?database=database&max_block_size=1',
        ];
    }

    public function testParamParsed(): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $now = new DateTimeImmutable();

        $request = $requestFactory->prepareSqlRequest(
            "SELECT {p1:String}, {p_2:DateTime('UTC')}",
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(
                [
                    'p1' => 'value1',
                    'p_2' => $now,
                ],
            ),
        );

        $body = $request->getBody()->__toString();
        self::assertStringContainsString('param_p1', $body);
        self::assertMatchesRegularExpression(
            '~Content-Disposition: form-data; name="param_p_2"\r\n(?:Content-Length: \d+\r\n)?\r\n'
                . $now->getTimestamp() . '~',
            $body,
        );
    }

    #[DataProvider('provideIgnoredPlaceholderIsNotBound')]
    public function testIgnoredPlaceholderIsNotBound(string $sql): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            $sql,
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(['context' => '{context:UnknownType}']),
        );

        self::assertSame($sql, $request->getBody()->__toString());
    }

    /** @phpstan-return Generator<string, array{string}> */
    public static function provideIgnoredPlaceholderIsNotBound(): Generator
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
        yield 'heredoc' => ['SELECT $$\' {context:UnknownType} $$'];
        yield 'tagged heredoc' => ['SELECT $sql$\' {context:UnknownType} $other$ $sql$'];
        yield 'dash comment' => ["SELECT 1 -- '{context:UnknownType}\n"];
        yield 'comment at end of input' => ['SELECT 1 -- {context:UnknownType}'];
        yield 'slash comment' => ["SELECT 1 // '{context:UnknownType}\n"];
        yield 'hash comment' => ["SELECT 1 # '{context:UnknownType}\n"];
        yield 'shebang comment' => ["#! {context:UnknownType}\nSELECT 1"];
        yield 'block comment' => ["SELECT 1 /* '{context:UnknownType} */"];
        yield 'nested block comment' => ['SELECT 1 /* /* nested */ {context:UnknownType} */'];
    }

    #[DataProvider('provideParamParsedWithIgnoredPlaceholder')]
    public function testParamParsedWithIgnoredPlaceholder(string $sql): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            $sql,
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(['context' => 'value']),
        );

        $body = $request->getBody()->__toString();
        self::assertStringContainsString($sql, $body);
        self::assertMatchesRegularExpression(
            '~Content-Disposition: form-data; name="param_context"\r\n(?:Content-Length: \d+\r\n)?\r\nvalue\r\n~',
            $body,
        );
    }

    /** @phpstan-return Generator<string, array{string}> */
    public static function provideParamParsedWithIgnoredPlaceholder(): Generator
    {
        yield 'literal before real parameter' => ["SELECT '{context:UnknownType}', {context:String}"];
        yield 'literal after real parameter' => ["SELECT {context:String}, '{context:UnknownType}'"];
        yield 'parameter after escaped backslash' => [
            <<<'SQL'
            SELECT '\\', {context:String}
            SQL,
        ];

        yield 'parameter after line comment' => ["SELECT -- {context:UnknownType}\n{context:String}"];
        yield 'parameter after nested comment' => ['SELECT /* /* nested */ {context:UnknownType} */ {context:String}'];

        yield 'parameter after heredoc' => ['SELECT $sql${context:UnknownType}$sql$, {context:String}'];
        yield 'parameter after empty heredoc' => ['SELECT $$$$, {context:String}'];
        yield 'parameter after unicode identifier' => ['SELECT “😀{context:UnknownType}”, {context:String}'];
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

    public function testMultipleNestedParamsParsed(): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            'SELECT {serverIds:Array(UUID)},{sensorIds:Array(Array(UUID))}',
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(
                [
                    'serverIds' => ['c8965e35-e785-4b05-a675-000000000000'],
                    'sensorIds' => [['c8965e35-e785-4b05-a675-111111111111']],
                ],
            ),
        );

        $body = $request->getBody()->__toString();
        self::assertStringContainsString('param_serverIds', $body);
        self::assertStringContainsString('param_sensorIds', $body);
    }

    public function testNestedDateTime64ParamIsQuoted(): void
    {
        $dateTime = new DateTimeImmutable('2026-07-30 12:00:00.123456+02:00');

        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            'SELECT {inputs:Array(Tuple(DateTime64(6), UUID))}',
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(
                [
                    'inputs' => [
                        [
                            $dateTime,
                            'c8965e35-e785-4b05-a675-000000000000',
                        ],
                    ],
                ],
            ),
        );

        self::assertStringContainsString(
            "('" . $dateTime->format('U.u') . "','c8965e35-e785-4b05-a675-000000000000')",
            $request->getBody()->__toString(),
        );
    }

    public function testTopLevelDateTime64ParamRemainsNumeric(): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            'SELECT {value:DateTime64(6)}',
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(
                [
                    'value' => new DateTimeImmutable('2026-07-30 12:00:00.123456'),
                ],
            ),
        );

        self::assertStringContainsString('1785412800.123456', $request->getBody()->__toString());
    }

    public function testNullableNestedDateTime64ParamIsQuoted(): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            'SELECT {inputs:Array(Nullable(DateTime64(6)))}',
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(
                [
                    'inputs' => [
                        new DateTimeImmutable('2026-07-30 12:00:00.123456'),
                    ],
                ],
            ),
        );

        self::assertStringContainsString(
            "['1785412800.123456']",
            $request->getBody()->__toString(),
        );
    }

    /** @param list<string> $values */
    #[DataProvider('provideNestedIpParameters')]
    public function testNestedIpParametersAreQuoted(string $type, array $values, string $expected): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            sprintf('SELECT {inputs:Array(%s)}', $type),
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(['inputs' => $values]),
        );

        self::assertStringContainsString($expected, $request->getBody()->__toString());
    }

    /** @return Generator<string, array{string, list<string>, string}> */
    public static function provideNestedIpParameters(): Generator
    {
        yield 'IPv4' => ['IPv4', ['192.0.2.1', '198.51.100.1'], "['192.0.2.1','198.51.100.1']"];
        yield 'IPv6' => ['IPv6', ['::ffff:192.0.2.1', '2001:db8::1'], "['::ffff:192.0.2.1','2001:db8::1']"];
    }
}
