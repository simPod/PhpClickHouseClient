<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\InvalidHeaderException;
use Amp\Pipeline\Queue;
use Generator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Client\Http\CancellationStream;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function Amp\async;

#[CoversClass(PsrClickHouseAsyncClient::class)]
#[CoversClass(CancellationStream::class)]
final class PsrClickHouseAsyncClientCancellationTest extends TestCaseBase
{
    /**
     * @param 'select'|'selectWithParams'|'selectStream'|'selectStreamWithParams' $method
     */
    #[DataProvider('provideRequestCancellation')]
    public function testRequestCancellation(string $method): void
    {
        /** @var DeferredFuture<null> $started */
        $started = new DeferredFuture();
        /** @var DeferredFuture<Response> $pending */
        $pending  = new DeferredFuture();
        $delegate = new class ($started, $pending) implements DelegateHttpClient {
            /**
             * @param DeferredFuture<null>     $started
             * @param DeferredFuture<Response> $pending
             */
            public function __construct(private DeferredFuture $started, private DeferredFuture $pending)
            {
            }

            public function request(Request $request, Cancellation $cancellation): Response
            {
                $this->started->complete();

                return $this->pending->getFuture()->await($cancellation);
            }
        };

        $client       = self::createClient($delegate);
        $cancellation = new DeferredCancellation();
        $token        = $cancellation->getCancellation();
        $format       = new TabSeparated();

        $future = match ($method) {
            'select' => $client->select('SELECT 1', $format, cancellation: $token),
            'selectWithParams' => $client->selectWithParams('SELECT 1', [], $format, cancellation: $token),
            'selectStream' => $client->selectStream('SELECT 1', $format, cancellation: $token),
            'selectStreamWithParams' => $client->selectStreamWithParams('SELECT 1', [], $format, cancellation: $token),
        };

        $started->getFuture()->await();
        $cancellation->cancel();

        $this->expectException(CancelledException::class);
        $future->await();
    }

    /** @phpstan-return Generator<string, array{'select'|'selectWithParams'|'selectStream'|'selectStreamWithParams'}> */
    public static function provideRequestCancellation(): Generator
    {
        yield 'buffered' => ['select'];
        yield 'buffered with parameters' => ['selectWithParams'];
        yield 'streamed' => ['selectStream'];
        yield 'streamed with parameters' => ['selectStreamWithParams'];
    }

    public function testAlreadyCancelledRequestIsNotSent(): void
    {
        $delegate = new class implements DelegateHttpClient {
            public int $requests = 0;

            /** @throws InvalidHeaderException */
            public function request(Request $request, Cancellation $cancellation): Response
            {
                ++$this->requests;

                return new Response('1.1', 200, null, [], "1\n", $request);
            }
        };

        $cancellation = new DeferredCancellation();
        $cancellation->cancel();

        $this->expectException(CancelledException::class);

        try {
            self::createClient($delegate)->select(
                'SELECT 1',
                new TabSeparated(),
                cancellation: $cancellation->getCancellation(),
            )->await();
        } finally {
            self::assertSame(0, $delegate->requests);
        }
    }

    #[DataProvider('provideBodyCancellation')]
    public function testBodyCancellation(bool $streamedError): void
    {
        /** @var Queue<string> $queue */
        $queue    = new Queue();
        $consumed = $queue->pushAsync("1\n");
        $source   = new ReadableIterableStream($queue->iterate());
        $logger   = $this->createMock(SqlLogger::class);
        $logger->expects(self::once())->method('startQuery');
        $logger->expects(self::once())->method('stopQuery');

        $client = self::createClient(
            self::respondWith($source, $streamedError ? 500 : 200),
            $logger,
        );

        $cancellation = new DeferredCancellation();
        $token        = $cancellation->getCancellation();
        $future       = $streamedError
            ? $client->selectStream('SELECT 1', new TabSeparated(), cancellation: $token)
            : $client->select('SELECT 1', new TabSeparated(), cancellation: $token);

        // One chunk has been consumed; the operation is now waiting for the unfinished body, not headers.
        $consumed->await();
        self::assertFalse($future->isComplete());
        $cancellation->cancel();

        $this->expectException(CancelledException::class);

        try {
            $future->await();
        } finally {
            self::assertTrue($source->isClosed());
        }
    }

    /** @phpstan-return Generator<string, array{bool}> */
    public static function provideBodyCancellation(): Generator
    {
        yield 'buffered success response' => [false];
        yield 'streamed error response' => [true];
    }

    public function testReturnedPayloadRemainsCancellable(): void
    {
        /** @var Queue<string> $queue */
        $queue        = new Queue();
        $source       = new ReadableIterableStream($queue->iterate());
        $cancellation = new DeferredCancellation();
        $payload      = self::createClient(self::respondWith($source))->selectStream(
            'SELECT 1',
            new TabSeparated(),
            cancellation: $cancellation->getCancellation(),
        )->await();

        /** @var DeferredFuture<null> $started */
        $started = new DeferredFuture();
        $read    = async(static function () use ($started, $payload): string|null {
            $started->complete();

            return $payload->read();
        });

        $started->getFuture()->await();
        $cancellation->cancel();

        $this->expectException(CancelledException::class);

        try {
            $read->await();
        } finally {
            self::assertTrue($source->isClosed());
        }
    }

    private static function respondWith(ReadableStream|string $body, int $status = 200): DelegateHttpClient
    {
        return new class ($body, $status) implements DelegateHttpClient {
            public function __construct(private ReadableStream|string $body, private int $status)
            {
            }

            /** @throws InvalidHeaderException */
            public function request(Request $request, Cancellation $cancellation): Response
            {
                return new Response('1.1', $this->status, null, [], $this->body, $request);
            }
        };
    }

    private static function createClient(
        DelegateHttpClient $delegate,
        SqlLogger|null $logger = null,
    ): PsrClickHouseAsyncClient {
        $psr17Factory = new Psr17Factory();

        return new PsrClickHouseAsyncClient(
            new HttpClient($delegate, []),
            new RequestFactory(
                new ParamValueConverterRegistry(),
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
                'https://clickhouse.example',
            ),
            new PsrAdapter($psr17Factory, $psr17Factory),
            sqlLogger: $logger,
        );
    }
}
