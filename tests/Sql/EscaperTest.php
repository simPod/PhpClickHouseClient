<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Sql\Escaper;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(Escaper::class)]
final class EscaperTest extends TestCaseBase
{
    public function testEscape(): void
    {
        self::assertSame('test', Escaper::escape('test'));
        self::assertSame("t\\n\\0\\r\\test\\'", Escaper::escape("t\n\0\r\test'"));
    }

    public function testQuoteIdentifier(): void
    {
        self::assertSame('`z`', Escaper::quoteIdentifier('z'));
        self::assertSame("`a\\`\\' `", Escaper::quoteIdentifier("a`' "));
    }
}
