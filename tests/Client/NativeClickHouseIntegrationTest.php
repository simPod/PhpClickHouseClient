<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SimPod\ClickHouseClient\Client\Native\Column;
use SimPod\ClickHouseClient\Client\Native\ConnectionConfig;
use SimPod\ClickHouseClient\Client\NativeClickHouseClient;
use SimPod\ClickHouseClient\Settings\ArraySettingsProvider;

use function getenv;

#[CoversClass(NativeClickHouseClient::class)]
final class NativeClickHouseIntegrationTest extends TestCase
{
    private NativeClickHouseClient $client;

    public function testSelectWithSettingsAndParameters(): void
    {
        $label  = "a'b\\c\n";
        $body   = "body\\t\n\0";
        $result = $this->client->selectWithParams(
            'SELECT toUInt8(number + :offset) AS id, {label:String} AS label, '
                . '{missing:Nullable(String)} AS missing, '
                . '{missingNumber:Nullable(Int16)} AS missing_number, '
                . 'CAST([1, 65535] AS Array(UInt16)) AS items, '
                . 'CAST(tuple(toInt8(-3), \'x\') AS Tuple(Int8, String)) AS pair, '
                . 'toUInt64(\'18446744073709551615\') AS large, '
                . 'CAST(\'ab\' AS FixedString(4)) AS fixed, '
                . 'toInt8({kind:Enum8(\'a\\\\t\' = 1)}) AS kind, '
                . '{_request_body:String} AS body, '
                . 'toUInt64(getSetting(\'max_block_size\')) AS block_size '
                . 'FROM numbers(2) ORDER BY number',
            [
                'offset' => 7,
                'label' => $label,
                'missing' => null,
                'missingNumber' => null,
                'kind' => 'a\\t',
                '_request_body' => $body,
            ],
            new ArraySettingsProvider(['max_block_size' => 1, 'max_threads' => 1]),
        );

        self::assertEquals(
            [
                new Column('id', 'UInt8'),
                new Column('label', 'String'),
                new Column('missing', 'Nullable(String)'),
                new Column('missing_number', 'Nullable(Int16)'),
                new Column('items', 'Array(UInt16)'),
                new Column('pair', 'Tuple(Int8, String)'),
                new Column('large', 'UInt64'),
                new Column('fixed', 'FixedString(4)'),
                new Column('kind', 'Int8'),
                new Column('body', 'String'),
                new Column('block_size', 'UInt64'),
            ],
            $result->columns,
        );
        $expectedValues = [$label, null, null, [1, 65535], [-3, 'x'], '18446744073709551615', "ab\0\0", 1, $body, 1];
        self::assertSame([[7, ...$expectedValues], [8, ...$expectedValues]], $result->rows);
    }

    public function testInsertBatchesIntoTemporaryTable(): void
    {
        // Temporary tables belong to this connection and disappear when it closes.
        $this->client->executeQueryWithParams(
            'CREATE TEMPORARY TABLE native_batches '
                . '(id UInt8, label Nullable(String) DEFAULT :fallback, items Array(Int16))',
            ['fallback' => 'fallback-label'],
        );
        $rows = static function (): Generator {
            yield ["a'b\\c", [-1, 2], 7];
            yield [null, [], 9];
            yield ["\0last", [32767], 11];
        };
        $this->client->insert('native_batches', $rows(), ['label', 'items', 'id']);
        $this->client->insert('native_batches', [[13]], ['id']);

        self::assertSame(
            [[7, "a'b\\c", [-1, 2]], [9, null, []], [11, "\0last", [32767]], [13, 'fallback-label', []]],
            $this->client->select('SELECT id, label, items FROM native_batches ORDER BY id')->rows,
        );
    }

    protected function setUp(): void
    {
        $caFile       = self::environment('CLICKHOUSE_TCP_CA_FILE', '');
        $serverName   = self::environment('CLICKHOUSE_TCP_SERVER_NAME', '');
        $this->client = new NativeClickHouseClient(
            new ConnectionConfig(
                self::environment('CLICKHOUSE_TCP_HOST', '127.0.0.1'),
                (int) self::environment('CLICKHOUSE_TCP_PORT', '9000'),
                self::environment('CLICKHOUSE_DATABASE', 'default'),
                self::environment('CLICKHOUSE_USER', 'default'),
                self::environment('CLICKHOUSE_PASSWORD', ''),
                tls: self::environment('CLICKHOUSE_TCP_TLS', '0') === '1',
                caFile: $caFile === '' ? null : $caFile,
                serverName: $serverName === '' ? null : $serverName,
            ),
            defaultSettings: new ArraySettingsProvider(['max_block_size' => 100]),
            insertBlockSize: 2,
        );
    }

    protected function tearDown(): void
    {
        $this->client->close();
    }

    private static function environment(string $name, string $fallback): string
    {
        $value = getenv($name);

        return $value === false ? $fallback : $value;
    }
}
