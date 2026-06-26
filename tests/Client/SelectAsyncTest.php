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
use SimPod\ClickHouseClient\Format\Json;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Tests\ClickHouseVersion;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

use function Amp\Future\await;

#[CoversClass(RequestFactory::class)]
#[CoversClass(PsrClickHouseAsyncClient::class)]
#[CoversClass(ServerError::class)]
#[CoversClass(Json::class)]
#[CoversClass(TabSeparated::class)]
final class SelectAsyncTest extends TestCaseBase
{
    use WithClient;

    public function testAsyncSelect(): void
    {
        $client = self::$asyncClient;

        $sql = <<<'CLICKHOUSE'
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE;

        /** @var Json<array{number: int|string}> $format */
        $format = new Json();

        $futures = [
            $client->select($sql, $format),
            $client->select($sql, $format),
        ];

        /**
         * @var array{
         *     \SimPod\ClickHouseClient\Output\Json<array{number: int|string}>,
         *     \SimPod\ClickHouseClient\Output\Json<array{number: int|string}>
         * } $jsonOutputs
         */
        $jsonOutputs = await($futures);

        $expectedData = ClickHouseVersion::quotes64BitIntegersInJson()
            ? [['number' => '0'], ['number' => '1']]
            : [['number' => 0], ['number' => 1]];

        self::assertSame($expectedData, $jsonOutputs[0]->data);
        self::assertSame($expectedData, $jsonOutputs[1]->data);
    }

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

    public function testSelectFromNonExistentTableExpectServerError(): void
    {
        $this->expectException(ServerError::class);

        self::$asyncClient->select('table', new TabSeparated())->await();
    }

    public function testAsyncSelectStream(): void
    {
        $stream = self::$asyncClient->selectStream('SELECT 1 AS data', new TabSeparated())->await();

        self::assertSame("1\n", $stream->buffer());
    }

    public function testAsyncSelectStreamWithParams(): void
    {
        $stream = self::$asyncClient->selectStreamWithParams(
            'SELECT {p1:UInt8} AS data',
            ['p1' => 3],
            new TabSeparated(),
        )->await();

        self::assertSame("3\n", $stream->buffer());
    }
}
