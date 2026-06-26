<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Output\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function iterator_to_array;

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

        self::assertSame(
            [['number' => '0'], ['number' => '1']],
            iterator_to_array($format->data, preserve_keys: false),
        );
    }

    public function testEachLineIsDecodedIndependently(): void
    {
        $format = new JsonEachRow("{\"number\":\"0\"} \n {\"number\":\"1\"}\n");

        self::assertSame(
            [['number' => '0'], ['number' => '1']],
            iterator_to_array($format->data, preserve_keys: false),
        );
    }
}
