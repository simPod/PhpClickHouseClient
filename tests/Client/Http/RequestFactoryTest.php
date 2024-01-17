<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Http;

use Generator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
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
            $uri,
        );

        $request = $requestFactory->prepareRequest(new RequestOptions(
            'SELECT 1',
            [],
            ['max_block_size' => 1],
            ['database' => 'database'],
        ));

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
}
