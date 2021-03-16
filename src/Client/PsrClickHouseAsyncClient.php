<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use DateTimeZone;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Http\Client\HttpAsyncClient;
use Psr\Http\Message\ResponseInterface;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;

class PsrClickHouseAsyncClient implements ClickHouseAsyncClient
{
    private HttpAsyncClient $asyncClient;

    private RequestFactory $requestFactory;

    private string $endpoint;

    /** @var array<string, float|int|string> */
    private array $defaultParameters;

    private SqlFactory $sqlFactory;

    /** @param array<string, float|int|string> $defaultParameters */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        string $endpoint,
        array $defaultParameters = [],
        ?DateTimeZone $clickHouseTimeZone = null
    ) {
        $this->asyncClient       = $asyncClient;
        $this->requestFactory    = $requestFactory;
        $this->endpoint          = $endpoint;
        $this->defaultParameters = $defaultParameters;
        $this->sqlFactory        = new SqlFactory(new ValueFormatter($clickHouseTimeZone));
    }

    /**
     * {@inheritDoc}
     */
    public function select(string $sql, Format $outputFormat, array $requestParameters = []) : PromiseInterface
    {
        $formatClause = $outputFormat::toSql();

        return $this->executeRequest(
            <<<CLICKHOUSE
$sql
$formatClause
CLICKHOUSE,
            $requestParameters,
            static function (ResponseInterface $response) use ($outputFormat) : Output {
                return $outputFormat::output((string) $response->getBody());
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function selectWithParameters(string $query, array $queryParameters, Format $outputFormat, array $requestParameters = []) : PromiseInterface
    {
        return $this->select(
            $this->sqlFactory->createWithParameters($query, $queryParameters),
            $outputFormat,
            $requestParameters
        );
    }

    /**
     * @param array<string, float|int|string> $requestParameters
     * @param callable(ResponseInterface): T $processResponse
     *
     * @return PromiseInterface<T>
     *
     * @template T
     */
    private function executeRequest(string $sql, array $requestParameters, callable $processResponse) : PromiseInterface
    {
        $request = $this->requestFactory->prepareRequest(
            $this->endpoint,
            new RequestOptions(
                $sql,
                $this->defaultParameters,
                $requestParameters
            )
        );

        /** @var PromiseInterface<ResponseInterface> $promise */
        $promise = Create::promiseFor($this->asyncClient->sendAsyncRequest($request));

        return $promise->then(
        /** @return T */
            static function (ResponseInterface $response) use ($processResponse) {
                if ($response->getStatusCode() !== 200) {
                    throw ServerError::fromResponse($response);
                }

                return $processResponse($response);
            }
        );
    }
}
