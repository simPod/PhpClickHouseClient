<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Sql\Type;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(UnsupportedParamType::class)]
final class UnsupportedParamTypeTest extends TestCaseBase
{
    public function testFromType(): void
    {
        self::assertStringContainsString(
            'Int32',
            UnsupportedParamType::fromType(Type::fromString('Int32'))->getMessage(),
        );
    }

    public function testFromString(): void
    {
        self::assertStringContainsString(
            'Int32',
            UnsupportedParamType::fromString('Int32')->getMessage(),
        );
    }
}
