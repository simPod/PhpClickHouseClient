<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Amp\ByteStream\Payload;
use Amp\Cancellation;
use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Request as AmpRequest;
use Error;
use Exception;
use Psr\Http\Message\RequestInterface;
use SimPod\ClickHouseClient\Client\Http\CancellationStream;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Client\Http\RequestSettings;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Settings\SettingsProvider;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use Throwable;

use function Amp\async;
use function uniqid;

class PsrClickHouseAsyncClient implements ClickHouseAsyncClient
{
    private SqlFactory $sqlFactory;

    /** @param array<non-empty-string, string|string[]> $defaultHeaders */
    public function __construct(
        private HttpClient $client,
        private RequestFactory $requestFactory,
        private PsrAdapter $psrAdapter,
        private array $defaultHeaders = [],
        private SqlLogger|null $sqlLogger = null,
        private SettingsProvider $defaultSettings = new EmptySettingsProvider(),
    ) {
        $this->sqlFactory = new SqlFactory(new ValueFormatter());
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     * @throws Exception
     */
    public function select(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
        Cancellation|null $cancellation = null,
    ): Future {
        return $this->selectWithParams($query, [], $outputFormat, $settings, $cancellation);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     * @throws Exception
     */
    public function selectWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
        Cancellation|null $cancellation = null,
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
            processResponse: static fn (string $body) => $outputFormat::output($body),
            cancellation: $cancellation,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param Format<Output<mixed>> $outputFormat
     *
     * @throws Error
     * @throws Exception
     */
    public function selectStream(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
        Cancellation|null $cancellation = null,
    ): Future {
        return $this->selectStreamWithParams($query, [], $outputFormat, $settings, $cancellation);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed>  $params
     * @param Format<Output<mixed>> $outputFormat
     *
     * @throws Error
     * @throws Exception
     */
    public function selectStreamWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
        Cancellation|null $cancellation = null,
    ): Future {
        $formatClause = $outputFormat::toSql();

        $sql = $this->sqlFactory->createWithParameters($query, $params);

        return $this->executeStreamRequest(
            <<<CLICKHOUSE
            $sql
            $formatClause
            CLICKHOUSE,
            params: $params,
            settings: $settings,
            cancellation: $cancellation,
        );
    }

    /**
     * @param array<string, mixed> $params
     * @param callable(string):T $processResponse
     *
     * @return Future<T>
     *
     * @throws Error
     * @throws Exception
     *
     * @template T
     */
    private function executeRequest(
        string $sql,
        array $params,
        SettingsProvider $settings,
        callable $processResponse,
        Cancellation|null $cancellation,
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

        /** @var Future<T> $future */
        $future = async(function () use ($processResponse, $request, $sql, $cancellation): mixed {
            $id = uniqid('', true);
            $this->sqlLogger?->startQuery($id, $sql);

            try {
                $cancellation?->throwIfRequested();
                $response = $this->client->request($this->toAmpRequest($request), $cancellation);
                $payload  = $response->getBody();

                try {
                    $cancellation?->throwIfRequested();
                    $body = $payload->buffer($cancellation);
                    $cancellation?->throwIfRequested();
                } finally {
                    $payload->close();
                }

                if (
                    $response->getStatus() !== 200
                    || ServerError::bodyContainsStreamedException(
                        $body,
                        $response->getHeader('X-ClickHouse-Exception-Tag') ?? '',
                    )
                ) {
                    throw ServerError::fromResponseContent($body, $response->getStatus());
                }

                return $processResponse($body);
            } finally {
                $this->sqlLogger?->stopQuery($id);
            }
        });

        return $future;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return Future<Payload>
     *
     * @throws Error
     * @throws Exception
     */
    private function executeStreamRequest(
        string $sql,
        array $params,
        SettingsProvider $settings,
        Cancellation|null $cancellation,
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

        /** @var Future<Payload> $future */
        $future = async(function () use ($request, $sql, $cancellation): Payload {
            $id = uniqid('', true);
            $this->sqlLogger?->startQuery($id, $sql);

            try {
                $cancellation?->throwIfRequested();
                $response = $this->client->request($this->toAmpRequest($request), $cancellation);
                $body     = $response->getBody();

                try {
                    $cancellation?->throwIfRequested();

                    if ($response->getStatus() !== 200) {
                        $content = $body->buffer($cancellation);
                        $cancellation?->throwIfRequested();

                        throw ServerError::fromResponseContent($content, $response->getStatus());
                    }

                    return $cancellation === null
                        ? $body
                        : new Payload(new CancellationStream($body, $cancellation));
                } catch (Throwable $exception) {
                    $body->close();

                    throw $exception;
                }
            } finally {
                $this->sqlLogger?->stopQuery($id);
            }
        });

        return $future;
    }

    /** @throws Error */
    private function toAmpRequest(RequestInterface $request): AmpRequest
    {
        $ampRequest = $this->psrAdapter->fromPsrRequest($request);

        foreach ($this->defaultHeaders as $name => $values) {
            $ampRequest->setHeader($name, $values);
        }

        // ClickHouse queries may be long-running and can stream large result sets.
        $ampRequest->setTransferTimeout(0);
        $ampRequest->setInactivityTimeout(0);
        $ampRequest->setBodySizeLimit(0);

        return $ampRequest;
    }
}
