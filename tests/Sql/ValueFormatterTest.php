<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use Safe\DateTime;
use Safe\DateTimeImmutable;
use SimPod\ClickHouseClient\Exception\UnsupportedValueType;
use SimPod\ClickHouseClient\Sql\Expression;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use stdClass;

/**
 * @covers \SimPod\ClickHouseClient\Sql\ValueFormatter
 */
final class ValueFormatterTest extends TestCaseBase
{
    /**
     * @param mixed $value
     *
     * @dataProvider providerFormat
     */
    public function testFormat(string $expectedValue, $value) : void
    {
        self::assertSame($expectedValue, ValueFormatter::format($value));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public function providerFormat() : iterable
    {
        yield 'integer' => ['1', 1];
        yield 'float .0' => ['1', 1.0];
        yield 'float .5' => ['1.5', 1.5];
        yield 'string' => ["'ping'", 'ping'];
        yield 'null' => ['IS NULL', null];
        yield 'array' => ["'ping',1,NULL", ['ping', 1, null]];
        yield 'DateTimeImmutable' => ["'2020-01-31 01:23:45'", new DateTimeImmutable('2020-01-31 01:23:45')];
        yield 'DateTime' => ["'2020-01-31 01:23:45'", new DateTime('2020-01-31 01:23:45')];
        yield 'Expression' => [
            "UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')",
            Expression::new("UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')"),
        ];

        yield 'Stringable' => [
            "'stringable'",
            new class() {
                public function __toString() : string
                {
                    return 'stringable';
                }
            },
        ];
    }

    public function testUnsupportedTypeThrows() : void
    {
        $this->expectException(UnsupportedValueType::class);

        ValueFormatter::format(new stdClass());
    }
}
