<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use SimPod\ClickHouseClient\Sql\Escaper;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Sql\Escaper */
final class EscaperTest extends TestCaseBase
{
    public function testEscape() : void
    {
        self::assertSame('test', Escaper::escape('test'));
        self::assertSame("t\\n\\0\\r\\test\\'", Escaper::escape("t\n\0\r\test'"));
    }

    public function testQuoteIdentifier() : void
    {
        self::assertSame('`z`', Escaper::quoteIdentifier('z'));
        self::assertSame("`a\\`\\' `", Escaper::quoteIdentifier("a`' "));
    }
}
