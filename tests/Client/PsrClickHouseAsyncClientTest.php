<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\InvalidHeaderException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(PsrClickHouseAsyncClient::class)]
#[CoversClass(ServerError::class)]
final class PsrClickHouseAsyncClientTest extends TestCaseBase
{
    public function testDefaultHeadersAreSent(): void
    {
        $delegate = new class implements DelegateHttpClient {
            public Request|null $request = null;

            /** @throws InvalidHeaderException */
            public function request(Request $request, Cancellation $cancellation): Response
            {
                $this->request = $request;

                return new Response('1.1', 200, null, [], "1\n", $request);
            }
        };

        $psr17Factory = new Psr17Factory();
        $client       = new PsrClickHouseAsyncClient(
            new HttpClient($delegate, []),
            new RequestFactory(
                new ParamValueConverterRegistry(),
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
                'https://clickhouse.example',
            ),
            new PsrAdapter($psr17Factory, $psr17Factory),
            [
                'X-ClickHouse-Key' => 'secret',
                'X-ClickHouse-User' => 'user',
            ],
        );

        $client->select('SELECT 1', new TabSeparated())->await();

        $request = $delegate->request;
        self::assertInstanceOf(Request::class, $request);
        self::assertSame('secret', $request->getHeader('X-ClickHouse-Key'));
        self::assertSame('user', $request->getHeader('X-ClickHouse-User'));
    }
}
