<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use Nette\Utils\Random;
use SimPod\ClickHouseClient\Client;
use function getenv;

trait WithClient
{
    /** @var Client */
    private $client;

    private $tmpPath;

    /** @var string|null */
    private $currentDbName;

    /**
     * @before
     */
    public function setupClickHouseClient() : void
    {
        $this->restartClickHouseClient();
    }

    public function restartClickHouseClient() : void
    {
        $databaseName = getenv('CLICKHOUSE_DATABASE');
        $config       = [
            'host'     => getenv('CLICKHOUSE_HOST'),
            'username' => getenv('CLICKHOUSE_USER'),
            'password' => getenv('CLICKHOUSE_PASSWORD'),
            'database' => $databaseName,
        ];

        $this->client = new Client($config);

        $this->currentDbName = 'clickhouse_client_test__' . Random::generate(8);
        $this->client->write(sprintf('DROP DATABASE IF EXISTS "%s"', $this->currentDbName));
        $this->client->write(sprintf('CREATE DATABASE "%s"', $this->currentDbName));
        $this->client->setDatabase($this->currentDbName);
    }

    /**
     * @after
     */
    public function tearDownDataBase() : void
    {
        $this->client->write(sprintf('DROP DATABASE IF EXISTS "%s"', $this->currentDbName));
    }
}
