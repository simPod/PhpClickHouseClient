<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use DateTimeZone;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Sql\Escaper;
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

class PsrClickHouseClient implements ClickHouseClient
{
    private ValueFormatter $valueFormatter;

    private SqlFactory $sqlFactory;

    /** @param array<string, float|int|string> $defaultSettings */
    public function __construct(
        private ClientInterface $client,
        private RequestFactory $requestFactory,
        private array $defaultSettings = [],
        DateTimeZone|null $clickHouseTimeZone = null,
    ) {
        $this->valueFormatter = new ValueFormatter($clickHouseTimeZone);
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

    public function insert(string $table, array $values, array|null $columns = null, array $settings = []): void
    {
        if ($values === []) {
            throw CannotInsert::noValues();
        }

        $table = Escaper::quoteIdentifier($table);

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
                INSERT INTO $table
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
                INSERT INTO $table
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

    public function insertWithFormat(string $table, Format $inputFormat, string $data, array $settings = []): void
    {
        $formatSql = $inputFormat::toSql();

        $table = Escaper::quoteIdentifier($table);

        try {
            $this->executeRequest(
                <<<CLICKHOUSE
                INSERT INTO $table $formatSql $data
                CLICKHOUSE,
                params: [],
                settings: $settings,
            );
        } catch (UnsupportedParamType) {
            absurd();
        }
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
        $request = $this->requestFactory->prepareRequest(
            new RequestOptions(
                $sql,
                $params,
                $this->defaultSettings,
                $settings,
            ),
        );

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw ServerError::fromResponse($response);
        }

        return $response;
    }
}
