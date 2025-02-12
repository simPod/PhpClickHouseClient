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
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function implode;

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
                ['max_block_size' => 1],
                ['database' => 'database'],
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
                [],
                [],
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
        self::assertStringContainsString(
            implode(
                "\r\n",
                [
                    'Content-Disposition: form-data; name="param_p_2"',
                    'Content-Length: 10',
                    '',
                    $now->getTimestamp(),
                ],
            ),
            $body,
        );
    }
}
