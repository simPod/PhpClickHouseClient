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

    /** @var array<string, float|int|string> */
    private array $defaultSettings;

    private SqlFactory $sqlFactory;

    /** @param array<string, float|int|string> $defaultSettings */
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        array $defaultSettings = [],
        ?DateTimeZone $clickHouseTimeZone = null,
    ) {
        $this->asyncClient     = $asyncClient;
        $this->requestFactory  = $requestFactory;
        $this->defaultSettings = $defaultSettings;
        $this->sqlFactory      = new SqlFactory(new ValueFormatter($clickHouseTimeZone));
    }

    /**
     * {@inheritDoc}
     */
    public function select(string $sql, Format $outputFormat, array $settings = []) : PromiseInterface
    {
        $formatClause = $outputFormat::toSql();

        return $this->executeRequest(
            <<<CLICKHOUSE
$sql
$formatClause
CLICKHOUSE,
            $settings,
            static function (ResponseInterface $response) use ($outputFormat) : Output {
                return $outputFormat::output($response->getBody()->__toString());
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function selectWithParams(
        string $sql,
        array $params,
        Format $outputFormat,
        array $settings = []
    ) : PromiseInterface {
        return $this->select(
            $this->sqlFactory->createWithParameters($sql, $params),
            $outputFormat,
            $settings
        );
    }

    /**
     * @param array<string, float|int|string> $settings
     * @param (callable(ResponseInterface):mixed)|null $processResponse
     */
    private function executeRequest(
        string $sql,
        array $settings = [],
        ?callable $processResponse = null
    ) : PromiseInterface {
        $request = $this->requestFactory->prepareRequest(
            new RequestOptions(
                $sql,
                $this->defaultSettings,
                $settings
            )
        );

        return Create::promiseFor($this->asyncClient->sendAsyncRequest($request))->then(
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
