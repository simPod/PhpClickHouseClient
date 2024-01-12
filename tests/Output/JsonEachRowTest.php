<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Output\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(JsonEachRow::class)]
final class JsonEachRowTest extends TestCaseBase
{
    public function testPropertiesAreSet(): void
    {
        $format = new JsonEachRow(
            <<<'JSON'
{"number":"0"}
{"number":"1"}

JSON,
        );

        self::assertSame([['number' => '0'], ['number' => '1']], $format->data);
    }
}
