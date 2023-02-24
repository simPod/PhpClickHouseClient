<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Client\Http\RequestFactory */
final class RequestFactoryTest extends TestCaseBase
{
    public function testPrepareRequest(): void
    {
        $psr17Factory   = new Psr17Factory();
        $requestFactory = new RequestFactory(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            'http://localhost:8123?format=JSON',
        );

        $request = $requestFactory->prepareRequest(new RequestOptions(
            'SELECT 1',
            ['max_block_size' => 1],
            ['database' => 'database'],
        ));

        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'http://localhost:8123?format=JSON&database=database&max_block_size=1',
            $request->getUri()->__toString(),
        );
        self::assertSame('SELECT 1', $request->getBody()->__toString());
    }
}
