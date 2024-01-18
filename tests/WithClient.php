<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseAsyncClient;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
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
    private static ClickHouseClient $client;

    private static ClickHouseAsyncClient $asyncClient;

    /** @internal */
    private static ClickHouseClient $controllerClient;

    private static string|null $currentDbName = null;

    #[BeforeClass]
    #[Before]
    public static function setupClickHouseClient(): void
    {
        static::restartClickHouseClient();
    }

    #[After]
    public function tearDownDataBase(): void
    {
        static::$controllerClient->executeQuery(sprintf(
            'DROP DATABASE IF EXISTS "%s"',
            static::$currentDbName,
        ));
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws ServerError
     */
    private static function restartClickHouseClient(): void
    {
        $databaseName = getenv('CLICKHOUSE_DATABASE');
        $username     = getenv('CLICKHOUSE_USER');
        $endpoint     = getenv('CLICKHOUSE_HOST');
        $password     = getenv('CLICKHOUSE_PASSWORD');

        assert(is_string($databaseName));
        assert(is_string($username));
        assert(is_string($endpoint));
        assert(is_string($password));

        static::$currentDbName = 'clickhouse_client_test__' . time();

        $headers = [
            'X-ClickHouse-User' => $username,
            'X-ClickHouse-Key' => $password,
        ];

        static::$controllerClient = new PsrClickHouseClient(
            new Psr18Client(
                new CurlHttpClient([
                    'base_uri' => $endpoint,
                    'headers' => $headers,
                    'query' => ['database' => $databaseName],
                ]),
            ),
            new RequestFactory(
                new ParamValueConverterRegistry(),
                new Psr17Factory(),
                new Psr17Factory(),
                new Psr17Factory(),
            ),
        );

        static::$client = new PsrClickHouseClient(
            new Psr18Client(
                new CurlHttpClient([
                    'base_uri' => $endpoint,
                    'headers' => $headers,
                    'query' => ['database' => static::$currentDbName],
                ]),
            ),
            new RequestFactory(
                new ParamValueConverterRegistry(),
                new Psr17Factory(),
                new Psr17Factory(),
                new Psr17Factory(),
            ),
        );

        static::$asyncClient = new PsrClickHouseAsyncClient(
            new HttplugClient(
                new CurlHttpClient([
                    'base_uri' => $endpoint,
                    'headers' => $headers,
                    'query' => ['database' => static::$currentDbName],
                ]),
            ),
            new RequestFactory(
                new ParamValueConverterRegistry(),
                new Psr17Factory(),
                new Psr17Factory(),
                new Psr17Factory(),
            ),
        );

        static::$controllerClient->executeQuery(sprintf('DROP DATABASE IF EXISTS "%s"', static::$currentDbName));
        static::$controllerClient->executeQuery(sprintf('CREATE DATABASE "%s"', static::$currentDbName));
    }
}
