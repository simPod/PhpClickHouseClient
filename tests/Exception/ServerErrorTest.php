<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Exception;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(ServerError::class)]
final class ServerErrorTest extends TestCaseBase
{
    public function testParseCode(): void
    {
        $psr17Factory = new Psr17Factory();
        $response     = $psr17Factory->createResponse(501)
            ->withBody(
                $psr17Factory->createStream(
                    // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
                    "Code: 48. DB::Exception: Table engine Distributed doesn't support mutations. (NOT_IMPLEMENTED) (version 24.3.12.75 (official build))",
                ),
            );

        $serverError = ServerError::fromResponse($response);

        self::assertSame(48, $serverError->getCode());
        self::assertSame(501, $serverError->httpStatusCode);
        self::assertSame('NOT_IMPLEMENTED', $serverError->clickHouseExceptionName);
    }

    public function testParseWithoutExceptionName(): void
    {
        $psr17Factory = new Psr17Factory();
        $response     = $psr17Factory->createResponse(500)
            ->withBody(
                $psr17Factory->createStream('Some unknown error'),
            );

        $serverError = ServerError::fromResponse($response);

        self::assertSame(0, $serverError->getCode());
        self::assertSame(500, $serverError->httpStatusCode);
        self::assertNull($serverError->clickHouseExceptionName);
    }

    public function testParseStreamedException(): void
    {
        $psr17Factory = new Psr17Factory();
        $response     = $psr17Factory->createResponse(200)
            ->withBody($psr17Factory->createStream(self::streamedExceptionBody()));

        $serverError = ServerError::fromResponse($response);

        self::assertSame(395, $serverError->getCode());
        self::assertSame(200, $serverError->httpStatusCode);
        self::assertSame('FUNCTION_THROW_IF_VALUE_IS_NON_ZERO', $serverError->clickHouseExceptionName);
    }

    public function testDetectStreamedExceptionWithHeaderTag(): void
    {
        self::assertTrue(ServerError::bodyContainsStreamedException(
            self::streamedExceptionBody(),
            'abcdefghijklmnop',
        ));
        self::assertFalse(ServerError::bodyContainsStreamedException(
            self::streamedExceptionBody(),
            'ponmlkjihgfedcba',
        ));
    }

    public function testDetectStreamedExceptionWithoutHeaderTag(): void
    {
        self::assertTrue(ServerError::bodyContainsStreamedException(self::streamedExceptionBody()));
        self::assertFalse(ServerError::bodyContainsStreamedException("1\n2\n3\n"));
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
