<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\InvalidHeaderException;
use GuzzleHttp\Psr7\NoSeekStream;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
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

    public function testSelectReturnsSuccessfulOkResponseAfterStreamedExceptionInspection(): void
    {
        $psr17Factory = new Psr17Factory();
        $response     = $psr17Factory->createResponse(200)
            ->withBody($psr17Factory->createStream("1\n"));

        $httpClient = new class ($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
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
        );

        $output = $client->select('SELECT 1', new TabSeparated());

        self::assertSame("1\n", $output->contents);
    }

    public function testSelectClosesResponseBodyAfterParsingIt(): void
    {
        $psr17Factory = new Psr17Factory();

        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::once())
            ->method('isSeekable')
            ->willReturn(true);
        $body->expects(self::exactly(2))
            ->method('__toString')
            ->willReturn("1\n");
        $body->expects(self::once())
            ->method('tell')
            ->willReturn(1);
        $body->expects(self::once())
            ->method('rewind');
        $body->expects(self::once())
            ->method('close');

        $response = $psr17Factory->createResponse(200)
            ->withBody($body);

        $httpClient = new class ($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
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
        );

        $output = $client->select('SELECT 1', new TabSeparated());

        self::assertSame("1\n", $output->contents);
    }

    public function testExecuteQueryClosesInspectedResponseBody(): void
    {
        $psr17Factory = new Psr17Factory();

        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::once())
            ->method('isSeekable')
            ->willReturn(true);
        $body->expects(self::once())
            ->method('__toString')
            ->willReturn('');
        $body->expects(self::once())
            ->method('tell')
            ->willReturn(1);
        $body->expects(self::once())
            ->method('rewind');
        $body->expects(self::once())
            ->method('close');

        $response = $psr17Factory->createResponse(200)
            ->withBody($body);

        $httpClient = new class ($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
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
        );

        $client->executeQuery('OPTIMIZE TABLE events');
    }

    public function testExecuteQueryClosesNonOkResponseBody(): void
    {
        $psr17Factory = new Psr17Factory();

        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::once())
            ->method('__toString')
            ->willReturn('Code: 60. DB::Exception: Table events does not exist. (UNKNOWN_TABLE)');
        $body->expects(self::once())
            ->method('close');

        $response = $psr17Factory->createResponse(404)
            ->withBody($body);

        $httpClient = new class ($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
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
        );

        $this->expectException(ServerError::class);

        $client->executeQuery('OPTIMIZE TABLE events');
    }

    public function testSelectClosesResponseBodyWhenOkResponseContainsStreamedException(): void
    {
        $psr17Factory = new Psr17Factory();

        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::once())
            ->method('isSeekable')
            ->willReturn(true);
        $body->expects(self::once())
            ->method('__toString')
            ->willReturn(self::streamedExceptionBody());
        $body->expects(self::once())
            ->method('close');

        $response = $psr17Factory->createResponse(200)
            ->withHeader('X-ClickHouse-Exception-Tag', 'abcdefghijklmnop')
            ->withBody($body);

        $httpClient = new class ($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
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
        );

        $this->expectException(ServerError::class);

        $client->select('SELECT throwIf(number = 2) FROM numbers(5)', new TabSeparated());
    }

    public function testSelectThrowsWhenStreamedExceptionInspectionWouldConsumeNonSeekableBody(): void
    {
        $psr17Factory = new Psr17Factory();
        $response     = $psr17Factory->createResponse(200)
            ->withBody(new NoSeekStream($psr17Factory->createStream("1\n")));

        $httpClient = new class ($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
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
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot inspect streamed ClickHouse exceptions on a non-seekable response body.');

        $client->select('SELECT 1', new TabSeparated());
    }

    public function testSelectStreamDoesNotPreScanResponseBody(): void
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

        $client = new PsrClickHouseClient(
            $httpClient,
            new RequestFactory(
                new ParamValueConverterRegistry(),
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
            ),
        );

        $stream = $client->selectStream('SELECT throwIf(number = 2) FROM numbers(5)', new TabSeparated());

        self::assertSame(self::streamedExceptionBody(), $stream->__toString());
    }

    public function testInsertPayloadClosesResponseBodyWithoutPreScanningIt(): void
    {
        $psr17Factory = new Psr17Factory();

        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::never())
            ->method('isSeekable');
        $body->expects(self::once())
            ->method('close');

        $response = $psr17Factory->createResponse(200)
            ->withBody($body);

        $httpClient = new class ($response) implements ClientInterface {
            public int $requestCount = 0;

            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ++$this->requestCount;

                return $this->response;
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
        );

        $client->insertPayload('events', new TabSeparated(), $psr17Factory->createStream("1\n"));

        self::assertSame(1, $httpClient->requestCount);
    }

    public function testAsyncSelectThrowsServerErrorWhenOkResponseContainsStreamedException(): void
    {
        $psr17Factory = new Psr17Factory();

        $delegate = new class (self::streamedExceptionBody()) implements DelegateHttpClient {
            public function __construct(private string $body)
            {
            }

            /** @throws InvalidHeaderException */
            public function request(Request $request, Cancellation $cancellation): Response
            {
                return new Response(
                    '1.1',
                    200,
                    null,
                    ['X-ClickHouse-Exception-Tag' => 'abcdefghijklmnop'],
                    $this->body,
                    $request,
                );
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
            new HttpClient($delegate, []),
            new RequestFactory(
                new ParamValueConverterRegistry(),
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
            ),
            new PsrAdapter($psr17Factory, $psr17Factory),
            [],
            $logger,
        );

        try {
            $client->select('SELECT throwIf(number = 2) FROM numbers(5)', new TabSeparated())->await();
            self::fail('ServerError was not thrown.');
        } catch (ServerError $serverError) {
            self::assertSame(395, $serverError->getCode());
            self::assertSame(200, $serverError->httpStatusCode);
            self::assertSame('FUNCTION_THROW_IF_VALUE_IS_NON_ZERO', $serverError->clickHouseExceptionName);
        }

        self::assertSame(1, $logger->startCount);
        self::assertSame(1, $logger->stopCount);
    }

    public function testAsyncSelectReturnsSuccessfulOkResponseAfterStreamedExceptionInspection(): void
    {
        $psr17Factory = new Psr17Factory();

        $delegate = new class implements DelegateHttpClient {
            /** @throws InvalidHeaderException */
            public function request(Request $request, Cancellation $cancellation): Response
            {
                return new Response('1.1', 200, null, [], "1\n", $request);
            }
        };

        $client = new PsrClickHouseAsyncClient(
            new HttpClient($delegate, []),
            new RequestFactory(
                new ParamValueConverterRegistry(),
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
            ),
            new PsrAdapter($psr17Factory, $psr17Factory),
        );

        $output = $client->select('SELECT 1', new TabSeparated())->await();

        self::assertSame("1\n", $output->contents);
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
