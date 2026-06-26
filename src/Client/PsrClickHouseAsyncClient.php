<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Amp\DeferredFuture;
use Amp\Future;
use Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Message;
use Http\Client\HttpAsyncClient;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Client\Http\RequestSettings;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Settings\SettingsProvider;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use Throwable;

use function uniqid;

class PsrClickHouseAsyncClient implements ClickHouseAsyncClient
{
    private SqlFactory $sqlFactory;

    public function __construct(
        private HttpAsyncClient $asyncClient,
        private RequestFactory $requestFactory,
        private SqlLogger|null $sqlLogger = null,
        private SettingsProvider $defaultSettings = new EmptySettingsProvider(),
    ) {
        $this->sqlFactory = new SqlFactory(new ValueFormatter());
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function select(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future {
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
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future {
        $formatClause = $outputFormat::toSql();

        $sql = $this->sqlFactory->createWithParameters($query, $params);

        return $this->executeRequest(
            <<<CLICKHOUSE
            $sql
            $formatClause
            CLICKHOUSE,
            params: $params,
            settings: $settings,
            processResponse: static fn (ResponseInterface $response) => $outputFormat::output(
                $response->getBody()->__toString(),
            ),
        );
    }

    /**
     * @param array<string, mixed> $params
     * @param (callable(ResponseInterface):mixed)|null $processResponse
     *
     * @throws Exception
     */
    private function executeRequest(
        string $sql,
        array $params,
        SettingsProvider $settings,
        callable|null $processResponse,
    ): Future {
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

        $deferred = new DeferredFuture();

        $this->asyncClient->sendAsyncRequest($request)->then(
            function (ResponseInterface $response) use ($deferred, $id, $processResponse): void {
                try {
                    $this->sqlLogger?->stopQuery($id);

                    if ($response->getStatusCode() !== 200) {
                        throw ServerError::fromResponse($response);
                    }

                    $body = $response->getBody();
                    if (! $body->isSeekable()) {
                        throw new RuntimeException(
                            'Cannot inspect streamed ClickHouse exceptions on a non-seekable response body.',
                        );
                    }

                    $bodyContent = $body->__toString();
                    if (
                        ServerError::bodyContainsStreamedException(
                            $bodyContent,
                            $response->getHeaderLine('X-ClickHouse-Exception-Tag'),
                        )
                    ) {
                        throw ServerError::fromResponse($response);
                    }

                    Message::rewindBody($response);

                    if ($processResponse === null) {
                        $deferred->complete($response);

                        return;
                    }

                    $deferred->complete($processResponse($response));
                } catch (Throwable $throwable) {
                    $deferred->error($throwable);
                }
            },
            function (mixed $reason) use ($deferred, $id): void {
                $this->sqlLogger?->stopQuery($id);

                $deferred->error(
                    $reason instanceof Throwable ? $reason : new RuntimeException('ClickHouse promise rejected'),
                );
            },
        );

        return $deferred->getFuture();
    }
}
