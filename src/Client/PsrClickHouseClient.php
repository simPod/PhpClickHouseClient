<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Client\Http\RequestSettings;
use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Schema\Table;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;

use function array_is_list;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_values;
use function implode;
use function is_array;
use function is_int;
use function SimPod\ClickHouseClient\absurd;
use function sprintf;
use function uniqid;

class PsrClickHouseClient implements ClickHouseClient
{
    private ValueFormatter $valueFormatter;

    private SqlFactory $sqlFactory;

    /** @param array<string, float|int|string> $defaultSettings */
    public function __construct(
        private ClientInterface $client,
        private RequestFactory $requestFactory,
        private SqlLogger|null $sqlLogger = null,
        private array $defaultSettings = [],
    ) {
        $this->valueFormatter = new ValueFormatter();
        $this->sqlFactory     = new SqlFactory($this->valueFormatter);
    }

    public function executeQuery(string $query, array $settings = []): void
    {
        try {
            $this->executeRequest($query, params: [], settings: $settings);
        } catch (UnsupportedParamType) {
            absurd();
        }
    }

    public function executeQueryWithParams(string $query, array $params, array $settings = []): void
    {
        $this->executeRequest(
            $this->sqlFactory->createWithParameters($query, $params),
            params: $params,
            settings: $settings,
        );
    }

    public function select(string $query, Format $outputFormat, array $settings = []): Output
    {
        try {
            return $this->selectWithParams($query, params: [], outputFormat: $outputFormat, settings: $settings);
        } catch (UnsupportedParamValue | UnsupportedParamType) {
            absurd();
        }
    }

    public function selectWithParams(string $query, array $params, Format $outputFormat, array $settings = []): Output
    {
        $formatClause = $outputFormat::toSql();

        $sql = $this->sqlFactory->createWithParameters($query, $params);

        $response = $this->executeRequest(
            <<<CLICKHOUSE
            $sql
            $formatClause
            CLICKHOUSE,
            params: $params,
            settings: $settings,
        );

        return $outputFormat::output($response->getBody()->__toString());
    }

    public function insert(Table|string $table, array $values, array|null $columns = null, array $settings = []): void
    {
        if ($values === []) {
            throw CannotInsert::noValues();
        }

        if (! $table instanceof Table) {
            $table = new Table($table);
        }

        $tableName = $table->fullName();

        if (is_array($columns) && ! array_is_list($columns)) {
            $columnsSql = sprintf('(%s)', implode(',', array_keys($columns)));

            $types = array_values($columns);

            $params = [];
            $pN     = 1;
            foreach ($values as $row) {
                foreach ($row as $value) {
                    $params['p' . $pN++] = $value;
                }
            }

            $pN        = 1;
            $valuesSql = implode(
                ',',
                array_map(
                    static function (array $row) use (&$pN, $types): string {
                        return sprintf(
                            '(%s)',
                            implode(',', array_map(static function ($i) use (&$pN, $types) {
                                return sprintf('{p%d:%s}', $pN++, $types[$i]);
                            }, array_keys($row))),
                        );
                    },
                    $values,
                ),
            );

            $this->executeRequest(
                <<<CLICKHOUSE
                INSERT INTO $tableName
                $columnsSql
                VALUES $valuesSql
                CLICKHOUSE,
                params: $params,
                settings: $settings,
            );

            return;
        }

        if ($columns === null) {
            $firstRow = $values[array_key_first($values)];
            $columns  = array_keys($firstRow);
            if (is_int($columns[0])) {
                $columns = null;
            }
        }

        $columnsSql = $columns === null ? '' : sprintf('(%s)', implode(',', $columns));

        $valuesSql = implode(
            ',',
            array_map(
                fn (array $map): string => sprintf(
                    '(%s)',
                    implode(',', $this->valueFormatter->mapFormat($map)),
                ),
                $values,
            ),
        );

        try {
            $this->executeRequest(
                <<<CLICKHOUSE
                INSERT INTO $tableName
                $columnsSql
                VALUES $valuesSql
                CLICKHOUSE,
                params: [],
                settings: $settings,
            );
        } catch (UnsupportedParamType) {
            absurd();
        }
    }

    public function insertWithFormat(
        Table|string $table,
        Format $inputFormat,
        string $data,
        array $settings = [],
    ): void {
        $formatSql = $inputFormat::toSql();

        if (! $table instanceof Table) {
            $table = new Table($table);
        }

        $tableName = $table->fullName();

        try {
            $this->executeRequest(
                <<<CLICKHOUSE
                INSERT INTO $tableName $formatSql $data
                CLICKHOUSE,
                params: [],
                settings: $settings,
            );
        } catch (UnsupportedParamType) {
            absurd();
        }
    }

    public function insertPayload(
        Table|string $table,
        Format $inputFormat,
        StreamInterface $payload,
        array $columns = [],
        array $settings = [],
    ): void {
        if ($payload->getSize() === 0) {
            throw CannotInsert::noValues();
        }

        $formatSql = $inputFormat::toSql();

        if (! $table instanceof Table) {
            $table = new Table($table);
        }

        $tableName = $table->fullName();

        $columnsSql = $columns === [] ? '' : sprintf('(%s)', implode(',', $columns));

        $sql = <<<CLICKHOUSE
        INSERT INTO $tableName $columnsSql $formatSql
        CLICKHOUSE;

        $request = $this->requestFactory->initRequest(
            new RequestSettings(
                $this->defaultSettings,
                $settings,
            ),
            ['query' => $sql],
        );

        try {
            $request = $request->withBody($payload);
        } catch (InvalidArgumentException) {
            absurd();
        }

        $this->sendHttpRequest($request, $sql);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, float|int|string> $settings
     *
     * @throws ServerError
     * @throws ClientExceptionInterface
     * @throws UnsupportedParamType
     */
    private function executeRequest(string $sql, array $params, array $settings): ResponseInterface
    {
        $request = $this->requestFactory->prepareSqlRequest(
            $sql,
            new RequestSettings(
                $this->defaultSettings,
                $settings,
            ),
            new RequestOptions(
                $params,
            ),
        );

        return $this->sendHttpRequest($request, $sql);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    private function sendHttpRequest(RequestInterface $request, string $sql): ResponseInterface
    {
        $id = uniqid('', true);
        $this->sqlLogger?->startQuery($id, $sql);

        try {
            $response = $this->client->sendRequest($request);
        } finally {
            $this->sqlLogger?->stopQuery($id);
        }

        if ($response->getStatusCode() !== 200) {
            throw ServerError::fromResponse($response);
        }

        return $response;
    }
}
