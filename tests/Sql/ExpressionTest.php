<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Sql\Expression;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(Expression::class)]
final class ExpressionTest extends TestCaseBase
{
    public function testNew(): void
    {
        self::assertSame(
            "UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')",
            (string) Expression::new("UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')"),
        );
    }

    public function testToString(): void
    {
        self::assertSame(
            "UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')",
            (string) Expression::new("UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')"),
        );
        self::assertSame(
            "UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')",
            Expression::new("UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')")->toString(),
        );
    }
}
