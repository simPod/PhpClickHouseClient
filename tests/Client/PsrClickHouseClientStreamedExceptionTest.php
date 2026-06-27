<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use Http\Client\HttpAsyncClient;
use Http\Promise\FulfilledPromise;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(RequestFactory::class)]
#[CoversClass(PsrClickHouseAsyncClient::class)]
#[CoversClass(PsrClickHouseClient::class)]
#[CoversClass(ServerError::class)]
final class PsrClickHouseClientStreamedExceptionTest extends TestCaseBase
{
    public function testSelectThrowsServerErrorWhenOkResponseContainsStreamedException(): void
    {
        $psr17Factory = new Psr17Factory();
        $response     = $psr17Factory->createResponse(200)
            ->withHeader('X-ClickHouse-Exception-Tag', 'abcdefghijklmnop')
            ->withBody($psr17Factory->createStream(self::streamedExceptionBody()));

        $httpClient = new class ($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $logger = new class implements SqlLogger {
            public int $startCount = 0;

            public int $stopCount = 0;

            public function startQuery(string $id, string $sql): void
            {
                ++$this->startCount;
            }

            public function stopQuery(string $id): void
            {
                ++$this->stopCount;
            }
        };

        $client = new PsrClickHouseClient(
            $httpClient,
            new RequestFactory(
                new ParamValueConverterRegistry(),
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
            ),
            $logger,
        );

        try {
            $client->select('SELECT throwIf(number = 2) FROM numbers(5)', new TabSeparated());
            self::fail('ServerError was not thrown.');
        } catch (ServerError $serverError) {
            self::assertSame(395, $serverError->getCode());
            self::assertSame(200, $serverError->httpStatusCode);
            self::assertSame('FUNCTION_THROW_IF_VALUE_IS_NON_ZERO', $serverError->clickHouseExceptionName);
        }

        self::assertSame(1, $logger->startCount);
        self::assertSame(1, $logger->stopCount);
    }

    public function testAsyncSelectThrowsServerErrorWhenOkResponseContainsStreamedException(): void
    {
        $psr17Factory = new Psr17Factory();
        $response     = $psr17Factory->createResponse(200)
            ->withHeader('X-ClickHouse-Exception-Tag', 'abcdefghijklmnop')
            ->withBody($psr17Factory->createStream(self::streamedExceptionBody()));

        $httpClient = new class ($response) implements HttpAsyncClient {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendAsyncRequest(RequestInterface $request): FulfilledPromise
            {
                return new FulfilledPromise($this->response);
            }
        };

        $logger = new class implements SqlLogger {
            public int $startCount = 0;

            public int $stopCount = 0;

            public function startQuery(string $id, string $sql): void
            {
                ++$this->startCount;
            }

            public function stopQuery(string $id): void
            {
                ++$this->stopCount;
            }
        };

        $client = new PsrClickHouseAsyncClient(
            $httpClient,
            new RequestFactory(
                new ParamValueConverterRegistry(),
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
            ),
            $logger,
        );

        try {
            $client->select('SELECT throwIf(number = 2) FROM numbers(5)', new TabSeparated())->wait();
            self::fail('ServerError was not thrown.');
        } catch (ServerError $serverError) {
            self::assertSame(395, $serverError->getCode());
            self::assertSame(200, $serverError->httpStatusCode);
            self::assertSame('FUNCTION_THROW_IF_VALUE_IS_NON_ZERO', $serverError->clickHouseExceptionName);
        }

        self::assertSame(1, $logger->startCount);
        self::assertSame(1, $logger->stopCount);
    }

    private static function streamedExceptionBody(): string
    {
        return <<<'CLICKHOUSE'
0	0
1	0
__exception__
abcdefghijklmnop
Code: 395. DB::Exception: Error while streaming. (FUNCTION_THROW_IF_VALUE_IS_NON_ZERO)
111 abcdefghijklmnop
__exception__

CLICKHOUSE;
    }
}
