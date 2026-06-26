<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Json;
use SimPod\ClickHouseClient\Format\TabSeparated;
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
