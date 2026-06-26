<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request as AmpRequest;
use Exception;
use Psr\Http\Message\RequestInterface;
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

    /** @param array<string, string|string[]> $defaultHeaders */
    public function __construct(
        private HttpClient $client,
        private RequestFactory $requestFactory,
        private array $defaultHeaders = [],
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
            processResponse: static fn (string $body): Output => $outputFormat::output($body),
        );
    }

    /**
     * @param array<string, mixed> $params
     * @param (callable(string):mixed)|null $processResponse
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

        return async(function () use ($processResponse, $request, $sql): mixed {
            $id = uniqid('', true);
            $this->sqlLogger?->startQuery($id, $sql);

            try {
                $response = $this->client->request($this->toAmpRequest($request));
                $body     = $response->getBody()->buffer();

                if (
                    $response->getStatus() !== 200
                    || ServerError::bodyContainsStreamedException(
                        $body,
                        $response->getHeader('X-ClickHouse-Exception-Tag') ?? '',
                    )
                ) {
                    throw ServerError::fromResponseContent($body, $response->getStatus());
                }

                if ($processResponse === null) {
                    return $body;
                }

                return $processResponse($body);
            } catch (Throwable $throwable) {
                $this->sqlLogger?->stopQuery($id);

                throw $throwable;
            }
        });
    }

    private function toAmpRequest(RequestInterface $request): AmpRequest
    {
        $ampRequest = new AmpRequest(
            $request->getUri(),
            $request->getMethod(),
            $request->getBody()->__toString(),
        );

        foreach ($this->defaultHeaders as $name => $values) {
            $ampRequest->setHeader($name, $values);
        }

        foreach ($request->getHeaders() as $name => $values) {
            $ampRequest->setHeader($name, $values);
        }

        return $ampRequest;
    }
}
