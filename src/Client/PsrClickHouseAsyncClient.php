<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use DateTimeZone;
use Exception;
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
    private SqlFactory $sqlFactory;

    /** @param array<string, float|int|string> $defaultSettings */
    public function __construct(
        private HttpAsyncClient $asyncClient,
        private RequestFactory $requestFactory,
        private array $defaultSettings = [],
        DateTimeZone|null $clickHouseTimeZone = null,
    ) {
        $this->sqlFactory = new SqlFactory(new ValueFormatter($clickHouseTimeZone));
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function select(string $query, Format $outputFormat, array $settings = []): PromiseInterface
    {
        return $this->selectWithParams($query, [], $outputFormat, $settings);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function selectWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        array $settings = [],
    ): PromiseInterface {
        $formatClause = $outputFormat::toSql();

        $sql = $this->sqlFactory->createWithParameters($query, $params);

        return $this->executeRequest(
            <<<CLICKHOUSE
            $sql
            $formatClause
            CLICKHOUSE,
            params: $params,
            settings: $settings,
            processResponse: static fn (ResponseInterface $response): Output => $outputFormat::output(
                $response->getBody()->__toString(),
            ),
        );
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, float|int|string> $settings
     * @param (callable(ResponseInterface):mixed)|null $processResponse
     *
     * @throws Exception
     */
    private function executeRequest(
        string $sql,
        array $params,
        array $settings = [],
        callable|null $processResponse = null,
    ): PromiseInterface {
        $request = $this->requestFactory->prepareRequest(
            new RequestOptions(
                $sql,
                $params,
                $this->defaultSettings,
                $settings,
            ),
        );

        return Create::promiseFor(
            $this->asyncClient->sendAsyncRequest($request),
        )
            ->then(
                static function (ResponseInterface $response) use ($processResponse) {
                    if ($response->getStatusCode() !== 200) {
                        throw ServerError::fromResponse($response);
                    }

                    if ($processResponse === null) {
                        return $response;
                    }

                    return $processResponse($response);
                },
            );
    }
}
