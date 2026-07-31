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
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(RequestFactory::class)]
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
            'SELECT {p1:String}, {p_2:DateTime}',
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
                            new DateTimeImmutable('2026-07-30 12:00:00.123456'),
                            'c8965e35-e785-4b05-a675-000000000000',
                        ],
                    ],
                ],
            ),
        );

        self::assertStringContainsString(
            "('2026-07-30 12:00:00.123456','c8965e35-e785-4b05-a675-000000000000')",
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

    public function testWrappedNestedDateTime64ParamIsQuoted(): void
    {
        $requestFactory = new RequestFactory(
            new ParamValueConverterRegistry(),
            new Psr17Factory(),
            new Psr17Factory(),
        );

        $request = $requestFactory->prepareSqlRequest(
            'SELECT {inputs:Array(Tuple(Nullable(DateTime64(6)), LowCardinality(DateTime64(6))))}',
            new RequestSettings(
                new EmptySettingsProvider(),
                new EmptySettingsProvider(),
            ),
            new RequestOptions(
                [
                    'inputs' => [
                        [
                            new DateTimeImmutable('2026-07-30 12:00:00.123456'),
                            new DateTimeImmutable('2026-07-30 12:01:00.123456'),
                        ],
                    ],
                ],
            ),
        );

        self::assertStringContainsString(
            "('2026-07-30 12:00:00.123456','2026-07-30 12:01:00.123456')",
            $request->getBody()->__toString(),
        );
    }
}
