<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimPod\ClickHouseClient\Client\Native\Column;
use SimPod\ClickHouseClient\Client\Native\Connection;
use SimPod\ClickHouseClient\Client\Native\ConnectionConfig;
use SimPod\ClickHouseClient\Client\NativeClickHouseClient;
use SimPod\ClickHouseClient\Exception\NativeServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Settings\ArraySettingsProvider;

use function fclose;
use function fgets;
use function is_resource;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;
use function stream_set_timeout;

use const PHP_BINARY;

#[CoversClass(NativeClickHouseClient::class)]
#[CoversClass(Connection::class)]
#[CoversClass(NativeServerError::class)]
final class NativeClickHouseClientTest extends TestCase
{
    private NativeClickHouseClient|null $client = null;

    /** @var resource|null */
    private $server = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    public function testSelect(): void
    {
        $client = $this->startServer(
            'select',
            new ArraySettingsProvider(['max_block_size' => 100, 'max_threads' => 1]),
        );
        $result = $client->selectWithParams(
            'SELECT {label:String}, {missing:Nullable(String)}, '
                . '{kind:Enum8(\'a\\\\t\' = 1)}, {_request_body:String}, :limit',
            ['label' => "a'b\\c", 'missing' => null, 'kind' => 'a\\t', '_request_body' => 'raw\\t', 'limit' => 9],
            new ArraySettingsProvider(['max_block_size' => 2]),
        );

        self::assertEquals([new Column('id', 'UInt8'), new Column('label', 'String')], $result->columns);
        self::assertSame([[7, 'one'], [9, 'two'], [11, 'three']], $result->rows);
        self::assertSame([[27, 'total']], $result->totals);
        self::assertSame([[7, 'min'], [11, 'max']], $result->extremes);

        // A zero-row schema must survive the empty Data boundary; the same socket is reusable after EOS.
        $empty = $client->select('SELECT empty');
        self::assertEquals([new Column('empty', 'UInt16')], $empty->columns);
        self::assertSame([], $empty->rows);
        self::assertSame([], $empty->totals);
        self::assertSame([], $empty->extremes);
        $this->finishServer();
    }

    public function testInsert(): void
    {
        $client = $this->startServer('insert');
        $rows   = static function (): Generator {
            yield ['one', 7];
            yield ['two', 9];
            yield ['three', 11];
        };

        $client->insert('events', $rows(), ['label', 'id']);
        self::assertSame([[3, 'done']], $client->select('SELECT inserted')->rows);
        $this->finishServer();
    }

    public function testNullParameterRequiresNullableType(): void
    {
        $this->client = new NativeClickHouseClient(new ConnectionConfig('127.0.0.1', 1));
        self::expectException(UnsupportedParamValue::class);
        $this->client->selectWithParams('SELECT {missing:String}', ['missing' => null]);
    }

    #[DataProvider('provideFailedSelect')]
    public function testFailedSelectDiscardsPartialRowsAndReconnects(string $scenario): void
    {
        $client = $this->startServer($scenario);
        try {
            $client->select('SELECT failing');
            self::fail('A partial result must not be returned before EndOfStream.');
        } catch (RuntimeException $error) {
            if ($scenario === 'exception') {
                self::assertInstanceOf(NativeServerError::class, $error);
                self::assertSame(241, $error->getCode());
                self::assertSame('MEMORY_LIMIT_EXCEEDED', $error->name);
                self::assertSame('Query exceeded its memory limit', $error->getMessage());
                self::assertSame('server.cpp:42', $error->serverStackTrace);
            } elseif ($scenario === 'budget') {
                self::assertStringContainsString('allocation budget', $error->getMessage());
            } else {
                self::assertNotInstanceOf(NativeServerError::class, $error);
                self::assertStringContainsString('closed connection', $error->getMessage());
            }
        }

        self::assertSame([[42, 'fresh']], $client->select('SELECT recovered')->rows);
        $this->finishServer();
    }

    /** @phpstan-return Generator<string, array{string}> */
    public static function provideFailedSelect(): Generator
    {
        yield 'server error after a complete data block' => ['exception'];
        yield 'connection closes inside a string column' => ['truncated'];
        yield 'individually valid blocks exceed total result budget' => ['budget'];
    }

    public function testInsertRejectsMismatchedRowsAndReconnects(): void
    {
        $client = $this->startServer('invalid-insert');
        try {
            $client->insert('events', [['missing-id']], ['label', 'id']);
            self::fail('An insert row must match the returned schema.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('match the server schema', $error->getMessage());
        }

        self::assertSame([[42, 'fresh']], $client->select('SELECT recovered')->rows);
        $this->finishServer();
    }

    protected function tearDown(): void
    {
        $this->client?->close();
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
    }

    private function startServer(
        string $scenario,
        ArraySettingsProvider $defaults = new ArraySettingsProvider([]),
    ): NativeClickHouseClient {
        $server = proc_open(
            [PHP_BINARY, __DIR__ . '/Native/server.php', $scenario],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($server);
        $this->server = $server;
        $this->pipes  = $pipes;
        stream_set_timeout($pipes[1], 5);
        $port = fgets($pipes[1]);
        self::assertNotFalse($port, 'Scripted server did not report its listening port.');

        $this->client = new NativeClickHouseClient(
            new ConnectionConfig('127.0.0.1', (int) $port, 'analytics', 'reader', 'test-password', readTimeout: 5),
            defaultSettings: $defaults,
            insertBlockSize: 2,
        );

        return $this->client;
    }

    private function finishServer(): void
    {
        $this->client?->close();
        $errors = stream_get_contents($this->pipes[2]);
        self::assertIsResource($this->server);
        $exitCode     = proc_close($this->server);
        $this->server = null;
        self::assertSame(0, $exitCode, $errors === false ? 'Cannot read scripted server errors.' : $errors);
    }
}
