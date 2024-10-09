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
    }
}
