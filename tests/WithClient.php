<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use Http\Client\Curl\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use SimPod\ClickHouseClient\Client\ClickHouseAsyncClient;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use function assert;
use function getenv;
use function is_string;
use function Safe\sprintf;
use function time;

trait WithClient
{
    /** @var ClickHouseClient */
    private $client;

    /** @var ClickHouseAsyncClient */
    private $asyncClient;

    /**
     * @internal
     *
     * @var ClickHouseClient
     */
    private $controllerClient;

    /** @var string|null */
    private $currentDbName;

    /** @before */
    public function setupClickHouseClient() : void
    {
        $this->restartClickHouseClient();
    }

    public function restartClickHouseClient() : void
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

        $defaultParameters = [
            'database' => $databaseName,
            'user'     => $username,
            'password' => $password,
        ];

        $this->controllerClient = new PsrClickHouseClient(
            new Client(),
            new RequestFactory(
                new Psr17Factory(),
                new Psr17Factory(),
                new Psr17Factory()
            ),
            new NullLogger(),
            $endpoint,
            $defaultParameters
        );

        $defaultParameters['database'] = $this->currentDbName;

        $this->client = new PsrClickHouseClient(
            new Client(),
            new RequestFactory(
                new Psr17Factory(),
                new Psr17Factory(),
                new Psr17Factory()
            ),
            new TestLogger(),
            $endpoint,
            $defaultParameters
        );

        $this->asyncClient = new PsrClickHouseAsyncClient(
            new Client(),
            new RequestFactory(
                new Psr17Factory(),
                new Psr17Factory(),
                new Psr17Factory()
            ),
            new TestLogger(),
            $endpoint,
            $defaultParameters
        );

        $this->controllerClient->executeQuery(sprintf('DROP DATABASE IF EXISTS "%s"', $this->currentDbName));
        $this->controllerClient->executeQuery(sprintf('CREATE DATABASE "%s"', $this->currentDbName));
    }

    /** @after */
    public function tearDownDataBase() : void
    {
        $this->controllerClient->executeQuery(sprintf('DROP DATABASE IF EXISTS "%s"', $this->currentDbName));
    }
}
