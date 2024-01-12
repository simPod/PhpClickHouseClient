<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Output\Basic;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(Basic::class)]
final class BasicTest extends TestCaseBase
{
    public function testContentsAreSet(): void
    {
        $contents = <<<'TEXT'
1

TEXT;
        self::assertSame($contents, (new Basic($contents))->contents);
    }
}
