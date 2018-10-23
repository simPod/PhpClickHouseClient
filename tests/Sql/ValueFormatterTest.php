<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use DateTimeZone;
use Safe\DateTimeImmutable;
use SimPod\ClickHouseClient\Exception\UnsupportedValueType;
use SimPod\ClickHouseClient\Sql\Expression;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use stdClass;

/** @covers \SimPod\ClickHouseClient\Sql\ValueFormatter */
final class ValueFormatterTest extends TestCaseBase
{
    /**
     * @param mixed $value
     *
     * @dataProvider providerFormat
     */
    public function testFormat(string $expectedValue, $value, ?string $paramName = null, ?string $sql = null) : void
    {
        self::assertSame(
            $expectedValue,
            (new ValueFormatter(new DateTimeZone('UTC')))->format($value, $paramName, $sql)
        );
    }

    /** @return iterable<string, array<mixed>> */
    public function providerFormat() : iterable
    {
        yield 'boolean' => ['1', true];
        yield 'integer' => ['1', 1];
        yield 'float .0' => ['1', 1.0];
        yield 'float .5' => ['1.5', 1.5];
        yield 'string' => ["'ping'", 'ping'];
        yield 'null' => ['IS NULL', null];
        yield 'array' => ["['a','b','c']", ['a', 'b', 'c']];
        yield 'array in array' => ["[['a']]", [['a']]];
        yield 'array with null' => ['[NULL]', [null]];
        yield 'array for IN' => ["'ping',1,NULL", ['ping', 1, null], 'list', 'SELECT * FROM table WHERE a IN (:list)'];
        yield 'no array for IN without sql' => ["['ping',1,NULL]", ['ping', 1, null], 'list'];
        yield 'DateTimeImmutable' => ["'2020-01-31 01:23:45'", new DateTimeImmutable('2020-01-31 01:23:45')];
        yield 'DateTimeImmutable different PHP and ClickHouse timezones' => [
            "'2020-01-31 01:23:45'",
            new DateTimeImmutable('2020-01-31 02:23:45', new DateTimeZone('Europe/Prague')),
        ];

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

    /**
     * @param array<mixed> $expectedValues
     * @param array<mixed> $values
     *
     * @dataProvider providerMapFormat
     */
    public function testMapFormat(array $expectedValues, array $values) : void
    {
        self::assertSame($expectedValues, (new ValueFormatter())->mapFormat($values));
    }

    /** @return iterable<string, array<array<mixed>>> */
    public function providerMapFormat() : iterable
    {
        yield 'string' => [["'ping'", "'pong'", 'NULL'], ['ping', 'pong', null]];
    }

    public function testUnsupportedTypeThrows() : void
    {
        $this->expectException(UnsupportedValueType::class);

        (new ValueFormatter())->format(new stdClass());
    }
}
