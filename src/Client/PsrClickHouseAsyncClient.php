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
    ): PromiseInterface {
        return $this->selectWithParams($query, [], $outputFormat, $settings);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function selectFuture(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future {
        return $this->selectWithParamsFuture($query, [], $outputFormat, $settings);
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
            processResponse: static fn (ResponseInterface $response) => $outputFormat::output(
                $response->getBody()->__toString(),
            ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function selectWithParamsFuture(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future {
        return self::futureForPromise($this->selectWithParams($query, $params, $outputFormat, $settings));
    }

    /**
     * @param PromiseInterface<T> $promise
     *
     * @return Future<T>
     *
     * @template T
     */
    private static function futureForPromise(PromiseInterface $promise): Future
    {
        $deferred = new DeferredFuture();

        $promise->then(
            static fn (mixed $value) => $deferred->complete($value),
            static fn (mixed $reason) => $deferred->error(
                $reason instanceof Throwable ? $reason : new RuntimeException('ClickHouse promise rejected'),
            ),
        );

        return $deferred->getFuture();
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
                        return $response;
                    }

                    return $processResponse($response);
                },
                fn () => $this->sqlLogger?->stopQuery($id),
            );
    }
}
