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

    /** @var array<string, string|array<string>> */
    private array $defaultHeaders;

    /** @var array<string, float|int|string> */
    private array $defaultQueryParams;

    private SqlFactory $sqlFactory;

    /**
     * @param array<string, string|array<string>> $defaultHeaders
     * @param array<string, float|int|string> $defaultQueryParams
     */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        string $endpoint,
        array $defaultHeaders = [],
        array $defaultQueryParams = [],
        ?DateTimeZone $clickHouseTimeZone = null
    ) {
        $this->asyncClient        = $asyncClient;
        $this->requestFactory     = $requestFactory;
        $this->endpoint           = $endpoint;
        $this->defaultHeaders     = $defaultHeaders;
        $this->defaultQueryParams = $defaultQueryParams;
        $this->sqlFactory         = new SqlFactory(new ValueFormatter($clickHouseTimeZone));
    }

    /**
     * {@inheritDoc}
     */
    public function select(
        string $sql,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestParameters = []
    ) : PromiseInterface {
        $formatClause = $outputFormat::toSql();

        return $this->executeRequest(
            <<<CLICKHOUSE
$sql
$formatClause
CLICKHOUSE,
            $requestHeaders,
            $requestParameters,
            static function (ResponseInterface $response) use ($outputFormat) : Output {
                return $outputFormat::output((string) $response->getBody());
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function selectWithParams(
        string $sql,
        array $statementParams,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : PromiseInterface {
        return $this->select(
            $this->sqlFactory->createWithParameters($sql, $statementParams),
            $outputFormat,
            $requestHeaders,
            $requestQueryParams
        );
    }

    /**
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestQueryParams
     * @param (callable(ResponseInterface):mixed)|null $processResponse
     */
    private function executeRequest(
        string $sql,
        array $requestHeaders = [],
        array $requestQueryParams = [],
        ?callable $processResponse = null
    ) : PromiseInterface {
        $request = $this->requestFactory->prepareRequest(
            $this->endpoint,
            new RequestOptions(
                $sql,
                $this->defaultHeaders,
                $requestHeaders,
                $this->defaultQueryParams,
                $requestQueryParams
            )
        );

        $promise = Create::promiseFor($this->asyncClient->sendAsyncRequest($request));

        return $promise->then(
            static function (ResponseInterface $response) use ($processResponse) {
                if ($response->getStatusCode() !== 200) {
                    throw ServerError::fromResponse($response);
                }

                if ($processResponse === null) {
                    return $response;
                }

                return $processResponse($response);
            }
        );
    }
}
