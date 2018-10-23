<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use DateTimeZone;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
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
    /** @var ClientInterface */
    private $client;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $endpoint;

    /** @var array<string, float|int|string> */
    private $defaultParameters;

    /** @var ValueFormatter */
    private $valueFormatter;

    /** @var SqlFactory */
    private $sqlFactory;

    /** @param array<string, float|int|string> $defaultParameters */
    public function __construct(
        ClientInterface $client,
        RequestFactory $requestFactory,
        LoggerInterface $logger,
        string $endpoint,
        array $defaultParameters = [],
        ?DateTimeZone $clickHouseTimeZone = null
    ) {
        $this->client            = $client;
        $this->requestFactory    = $requestFactory;
        $this->logger            = $logger;
        $this->endpoint          = $endpoint;
        $this->defaultParameters = $defaultParameters;
        $this->valueFormatter    = new ValueFormatter($clickHouseTimeZone);
        $this->sqlFactory        = new SqlFactory($this->valueFormatter);
    }

    public function executeQuery(string $query) : void
    {
        $this->executeRequest($query);
    }

    /**
     * {@inheritDoc}
     */
    public function executeQueryWithParameters(string $query, array $queryParameters) : void
    {
        $this->executeQuery($this->sqlFactory->createWithParameters($query, $queryParameters));
    }

    /**
     * {@inheritDoc}
     */
    public function select(string $query, Format $outputFormat, array $requestParameters = []) : Output
    {
        $formatClause = $outputFormat::toSql();

        $response = $this->executeRequest(
            <<<CLICKHOUSE
$query
$formatClause
CLICKHOUSE,
            $requestParameters
        );

        return $outputFormat::output((string) $response->getBody());
    }

    /**
     * {@inheritDoc}
     */
    public function selectWithParameters(string $query, array $queryParameters, Format $outputFormat, array $requestParameters = []) : Output
    {
        return $this->select(
            $this->sqlFactory->createWithParameters($query, $queryParameters),
            $outputFormat,
            $requestParameters
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

        $response = $this->executeRequest(
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

    /** @return array<string, float|int|string> */
    public function getDefaultParameters() : array
    {
        return $this->defaultParameters;
    }

    /** @param array<string, float|int|string> $defaultParameters */
    public function setDefaultParameters(array $defaultParameters) : void
    {
        $this->defaultParameters = $defaultParameters;
    }

    /** @param array<string, float|int|string> $requestParameters */
    private function executeRequest(string $sql, array $requestParameters = []) : ResponseInterface
    {
        $this->logger->debug($sql, $requestParameters);

        $request = $this->requestFactory->prepareRequest(
            $this->endpoint,
            new RequestOptions(
                $sql,
                $this->defaultParameters,
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
