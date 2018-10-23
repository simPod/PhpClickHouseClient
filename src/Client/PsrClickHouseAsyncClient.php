<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use GuzzleHttp\Promise\PromiseInterface;
use Http\Client\HttpAsyncClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Http\RequestFactory;
use SimPod\ClickHouseClient\Http\RequestOptions;
use SimPod\ClickHouseClient\Output\Output;
use function GuzzleHttp\Promise\promise_for;

class PsrClickHouseAsyncClient implements ClickHouseAsyncClient
{
    /** @var HttpAsyncClient */
    private $asyncClient;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var string */
    private $endpoint;

    /** @var array<string, float|int|string> */
    private $defaultParameters;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param array<string, float|int|string> $defaultParameters
     */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        LoggerInterface $logger,
        string $endpoint,
        array $defaultParameters = []
    ) {
        $this->asyncClient       = $asyncClient;
        $this->requestFactory    = $requestFactory;
        $this->logger            = $logger;
        $this->endpoint          = $endpoint;
        $this->defaultParameters = $defaultParameters;
    }

    public function select(string $sql, Format $outputFormat) : PromiseInterface
    {
        $formatClause = $outputFormat::toSql();

        return $this->executeRequest(
            <<<CLICKHOUSE
$sql
$formatClause
CLICKHOUSE,
            [],
            static function (ResponseInterface $response) use ($outputFormat) : Output {
                return $outputFormat::output((string) $response->getBody());
            }
        );
    }

    /**
     * @param array<string, float|int|string>        $requestParameters
     * @param callable(ResponseInterface):mixed|null $processResponse
     */
    private function executeRequest(string $sql, array $requestParameters = [], ?callable $processResponse = null) : PromiseInterface
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

        $promise = promise_for($this->asyncClient->sendAsyncRequest($request));

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
