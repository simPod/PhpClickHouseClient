<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Http\RequestFactory;
use SimPod\ClickHouseClient\Http\RequestOptions;
use SimPod\ClickHouseClient\Output\Output;
use function array_key_first;
use function array_keys;
use function array_map;
use function implode;
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

    /**
     * @param array<string, float|int|string> $defaultParameters
     */
    public function __construct(
        ClientInterface $client,
        RequestFactory $requestFactory,
        LoggerInterface $logger,
        string $endpoint,
        array $defaultParameters = []
    ) {
        $this->client            = $client;
        $this->requestFactory    = $requestFactory;
        $this->logger            = $logger;
        $this->endpoint          = $endpoint;
        $this->defaultParameters = $defaultParameters;
    }

    public function executeQuery(string $sql) : void
    {
        $response = $this->executeRequest($sql);

        $contents = (string) $response->getBody();

        return;
    }

    /**
     * {@inheritDoc}
     */
    public function select(string $sql, Format $outputFormat, array $requestParameters = []) : Output
    {
        $formatClause = $outputFormat::toSql();

        $response = $this->executeRequest(
            <<<CLICKHOUSE
$sql
$formatClause
CLICKHOUSE,
            $requestParameters
        );

        return $outputFormat::output((string) $response->getBody());
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
        }

        $columnsSql = implode(',', $columns);

        $valuesSql = implode(
            ',',
            array_map(
                static function (array $map) : string {
                    return sprintf(
                        '(%s)',
                        implode(',', $map)
                    );
                },
                $values
            )
        );

        $response = $this->executeRequest(
            <<<CLICKHOUSE
INSERT INTO $table
($columnsSql)
VALUES $valuesSql
CLICKHOUSE
        );
    }

    public function insertWithFormat(string $table, Format $inputFormat, string $data) : void
    {
        $formatSql = $inputFormat::toSql();

        $this->executeRequest(
            <<<CLICKHOUSE
INSERT INTO $table $formatSql $data
CLICKHOUSE
        );
    }

    /**
     * @param array<string, float|int|string> $requestParameters
     */
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

    /**
     * @return array<string, float|int|string>
     */
    public function getDefaultParameters() : array
    {
        return $this->defaultParameters;
    }

    /**
     * @param array<string, float|int|string> $defaultParameters
     */
    public function setDefaultParameters(array $defaultParameters) : void
    {
        $this->defaultParameters = $defaultParameters;
    }
}
