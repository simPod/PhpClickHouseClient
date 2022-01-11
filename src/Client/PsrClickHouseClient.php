<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use DateTimeZone;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Sql\Escaper;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;

use function array_key_first;
use function array_keys;
use function array_map;
use function implode;
use function is_int;
use function Safe\sprintf;

class PsrClickHouseClient implements ClickHouseClient
{
    private ClientInterface $client;

    private RequestFactory $requestFactory;

    private string $endpoint;

    /** @var array<string, string|array<string>> */
    private array $defaultHeaders;

    /** @var array<string, float|int|string> */
    private array $defaultQueryParams;

    private ValueFormatter $valueFormatter;

    private SqlFactory $sqlFactory;

    /**
     * @param array<string, string|array<string>> $defaultHeaders
     * @param array<string, float|int|string> $defaultQueryParams
     */
    public function __construct(
        ClientInterface $client,
        RequestFactory $requestFactory,
        string $endpoint,
        array $defaultHeaders = [],
        array $defaultQueryParams = [],
        ?DateTimeZone $clickHouseTimeZone = null
    ) {
        $this->client             = $client;
        $this->requestFactory     = $requestFactory;
        $this->endpoint           = $endpoint;
        $this->defaultHeaders     = $defaultHeaders;
        $this->defaultQueryParams = $defaultQueryParams;
        $this->valueFormatter     = new ValueFormatter($clickHouseTimeZone);
        $this->sqlFactory         = new SqlFactory($this->valueFormatter);
    }

    /**
     * {@inheritDoc}
     */
    public function executeQuery(string $query, array $requestHeaders = [], array $requestQueryParams = []) : void
    {
        $this->executeRequest($query, $requestHeaders, $requestQueryParams);
    }

    /**
     * {@inheritDoc}
     */
    public function executeQueryWithParameters(
        string $query,
        array $statementParams,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : void {
        $this->executeQuery(
            $this->sqlFactory->createWithParameters($query, $statementParams),
            $requestHeaders,
            $requestQueryParams
        );
    }

    /**
     * {@inheritDoc}
     */
    public function select(
        string $query,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : Output {
        $formatClause = $outputFormat::toSql();

        $response = $this->executeRequest(
            <<<CLICKHOUSE
$query
$formatClause
CLICKHOUSE,
            $requestHeaders,
            $requestQueryParams
        );

        return $outputFormat::output((string) $response->getBody());
    }

    /**
     * {@inheritDoc}
     */
    public function selectWithParams(
        string $query,
        array $statementParams,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : Output {
        return $this->select(
            $this->sqlFactory->createWithParameters($query, $statementParams),
            $outputFormat,
            $requestHeaders,
            $requestQueryParams
        );
    }

    /**
     * {@inheritDoc}
     */
    public function insert(string $table, array $values, ?array $columns = null) : void
    {
        if ($values === []) {
            throw CannotInsert::noValues();
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
                function (array $map) : string {
                    return sprintf(
                        '(%s)',
                        implode(',', $this->valueFormatter->mapFormat($map))
                    );
                },
                $values
            )
        );

        $table = Escaper::quoteIdentifier($table);

        $this->executeRequest(
            <<<CLICKHOUSE
INSERT INTO $table
$columnsSql
VALUES $valuesSql
CLICKHOUSE
        );
    }

    public function insertWithFormat(string $table, Format $inputFormat, string $data) : void
    {
        $formatSql = $inputFormat::toSql();

        $table = Escaper::quoteIdentifier($table);

        $this->executeRequest(
            <<<CLICKHOUSE
INSERT INTO $table $formatSql $data
CLICKHOUSE
        );
    }

    /**
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestParameters
     */
    private function executeRequest(
        string $sql,
        array $requestHeaders = [],
        array $requestParameters = []
    ) : ResponseInterface {
        $request = $this->requestFactory->prepareRequest(
            $this->endpoint,
            new RequestOptions(
                $sql,
                $this->defaultHeaders,
                $requestHeaders,
                $this->defaultQueryParams,
                $requestParameters
            )
        );

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw ServerError::fromResponse($response);
        }

        return $response;
    }
}
