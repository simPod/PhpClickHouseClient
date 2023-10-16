<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Safe\Exceptions\PcreException;
use SimPod\ClickHouseClient\Client\ClickHouseAsyncClient;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpClient\Psr18Client;

use function assert;
use function getenv;
use function is_string;
use function sprintf;
use function time;

trait WithClient
{
    private ClickHouseClient $client;

    private ClickHouseAsyncClient $asyncClient;

    /** @internal */
    private ClickHouseClient $controllerClient;

    private string|null $currentDbName = null;

    /** @before */
    public function setupClickHouseClient(): void
    {
        $this->restartClickHouseClient();
    }

    /** @after */
    public function tearDownDataBase(): void
    {
        $this->controllerClient->executeQuery(sprintf('DROP DATABASE IF EXISTS "%s"', $this->currentDbName));
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws PcreException
     * @throws ServerError
     */
    private function restartClickHouseClient(): void
    {
        $databaseName = getenv('CLICKHOUSE_DATABASE');
        $username     = getenv('CLICKHOUSE_USER');
        $endpoint     = getenv('CLICKHOUSE_HOST');
        $password     = getenv('CLICKHOUSE_PASSWORD');

        assert(is_string($databaseName));
        assert(is_string($username));
        assert(is_string($endpoint));
        assert(is_string($password));

        $this->currentDbName = 'clickhouse_client_test__' . time();

        $headers = [
            'X-ClickHouse-User' => $username,
            'X-ClickHouse-Key' => $password,
        ];

        $this->controllerClient = new PsrClickHouseClient(
            new Psr18Client(
                new CurlHttpClient([
                    'base_uri' => $endpoint,
                    'headers' => $headers,
                    'query' => ['database' => $databaseName],
                ]),
            ),
            new RequestFactory(
                new Psr17Factory(),
                new Psr17Factory(),
            ),
        );

        $this->client = new PsrClickHouseClient(
            new Psr18Client(
                new CurlHttpClient([
                    'base_uri' => $endpoint,
                    'headers' => $headers,
                    'query' => ['database' => $this->currentDbName],
                ]),
            ),
            new RequestFactory(
                new Psr17Factory(),
                new Psr17Factory(),
            ),
        );

        $this->asyncClient = new PsrClickHouseAsyncClient(
            new HttplugClient(
                new CurlHttpClient([
                    'base_uri' => $endpoint,
                    'headers' => $headers,
                    'query' => ['database' => $this->currentDbName],
                ]),
            ),
            new RequestFactory(
                new Psr17Factory(),
                new Psr17Factory(),
            ),
        );

        $this->controllerClient->executeQuery(sprintf('DROP DATABASE IF EXISTS "%s"', $this->currentDbName));
        $this->controllerClient->executeQuery(sprintf('CREATE DATABASE "%s"', $this->currentDbName));
    }
}
