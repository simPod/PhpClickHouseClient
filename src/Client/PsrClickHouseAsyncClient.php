<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use DateTimeZone;
use GuzzleHttp\Promise\PromiseInterface;
use Http\Client\HttpAsyncClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use function GuzzleHttp\Promise\promise_for;

class PsrClickHouseAsyncClient implements ClickHouseAsyncClient
{
    /** @var HttpAsyncClient */
    private $asyncClient;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $endpoint;

    /** @var array<string, float|int|string> */
    private $defaultParameters;

    /** @var SqlFactory */
    private $sqlFactory;

    /** @param array<string, float|int|string> $defaultParameters */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        LoggerInterface $logger,
        string $endpoint,
        array $defaultParameters = [],
        ?DateTimeZone $clickHouseTimeZone = null
    ) {
        $this->asyncClient       = $asyncClient;
        $this->requestFactory    = $requestFactory;
        $this->logger            = $logger;
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

    /**
     * @param array<string, float|int|string> $requestParameters
     * @param callable(ResponseInterface):mixed|null $processResponse
     */
    private function executeRequest(
        string $sql,
        array $requestParameters = [],
        ?callable $processResponse = null
    ) : PromiseInterface {
        $this->logger->debug($sql, $requestParameters);

        $request = $this->requestFactory->prepareRequest(
            $this->endpoint,
            new RequestOptions(
                $sql,
                $this->defaultParameters,
                $requestParameters
            )
        );

        $promise = promise_for($this->asyncClient->sendAsyncRequest($request));

        return $promise->then(
            /** @return mixed */
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
