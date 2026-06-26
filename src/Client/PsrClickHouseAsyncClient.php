<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Psr7\PsrAdapter;
use Exception;
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
     * @param callable(string):T $processResponse
     *
     * @return Future<T>
     *
     * @throws Exception
     *
     * @template T
     */
    private function executeRequest(
        string $sql,
        array $params,
        SettingsProvider $settings,
        callable $processResponse,
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
        $future = async(function () use ($processResponse, $request, $sql): mixed {
            $id = uniqid('', true);
            $this->sqlLogger?->startQuery($id, $sql);

            try {
                $ampRequest = $this->psrAdapter->fromPsrRequest($request);

                foreach ($this->defaultHeaders as $name => $values) {
                    $ampRequest->setHeader($name, $values);
                }

                $response = $this->client->request($ampRequest);
                $body     = $response->getBody()->buffer();

                $this->sqlLogger?->stopQuery($id);

                if ($response->getStatus() !== 200) {
                    throw ServerError::fromResponseContent($body, $response->getStatus());
                }

                return $processResponse($body);
            } catch (Throwable $throwable) {
                $this->sqlLogger?->stopQuery($id);

                throw $throwable;
            }
        });

        return $future;
    }
}
