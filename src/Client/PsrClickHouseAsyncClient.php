<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Http\Client\HttpAsyncClient;
use Psr\Http\Message\ResponseInterface;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Client\Http\RequestSettings;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;

use function uniqid;

class PsrClickHouseAsyncClient implements ClickHouseAsyncClient
{
    private SqlFactory $sqlFactory;

    /** @param array<string, float|int|string> $defaultSettings */
    public function __construct(
        private HttpAsyncClient $asyncClient,
        private RequestFactory $requestFactory,
        private SqlLogger|null $sqlLogger = null,
        private array $defaultSettings = [],
    ) {
        $this->sqlFactory = new SqlFactory(new ValueFormatter());
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

        $id = uniqid('', true);
        $this->sqlLogger?->startQuery($id, $sql);

        return Create::promiseFor(
            $this->asyncClient->sendAsyncRequest($request),
        )
            ->then(
                function (ResponseInterface $response) use ($id, $processResponse) {
                    $this->sqlLogger?->stopQuery($id);

                    if ($response->getStatusCode() !== 200) {
                        throw ServerError::fromResponse($response);
                    }

                    if ($processResponse === null) {
                        return $response;
                    }

                    return $processResponse($response);
                },
                fn () => $this->sqlLogger?->stopQuery($id),
            );
    }
}
