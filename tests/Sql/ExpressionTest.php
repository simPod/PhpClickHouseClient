<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use SimPod\ClickHouseClient\Sql\Expression;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Sql\Expression */
final class ExpressionTest extends TestCaseBase
{
    public function testNew() : void
    {
        self::assertSame(
            "UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')",
            (string) Expression::new("UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')")
        );
    }
}
