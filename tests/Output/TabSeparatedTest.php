<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Output;

use SimPod\ClickHouseClient\Output\TabSeparated;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Output\TabSeparated */
final class TabSeparatedTest extends TestCaseBase
{
    public function testContentsAreSet() : void
    {
        $contents = <<<TEXT
1

TEXT;
        self::assertSame($contents, (new TabSeparated($contents))->contents);
    }
}
