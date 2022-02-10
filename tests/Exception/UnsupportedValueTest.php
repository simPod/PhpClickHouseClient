<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Exception;

use Safe\DateTime;
use SimPod\ClickHouseClient\Exception\UnsupportedValue;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use stdClass;

use function Safe\opendir;

/** @covers \SimPod\ClickHouseClient\Exception\UnsupportedValue */
final class UnsupportedValueTest extends TestCaseBase
{
    /** @dataProvider providerType */
    public function testType(string $expectedMessage, mixed $value): void
    {
        $exception = UnsupportedValue::type($value);

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /** @return iterable<int, array{string, mixed}> */
    public function providerType(): iterable
    {
        yield [
            'Value of type "resource (stream)" is not supported as a parameter',
            opendir(__DIR__),
        ];

        yield [
            'Value of type "stdClass" is not supported as a parameter',
            new stdClass(),
        ];

        yield [
            'Value of type "Safe\DateTime" is not supported as a parameter',
            new DateTime(),
        ];
    }

    /** @dataProvider providerValue */
    public function testValue(string $expectedMessage, mixed $value): void
    {
        $exception = UnsupportedValue::value($value);

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /** @return iterable<int, array{string, mixed}> */
    public function providerValue(): iterable
    {
        yield [
            'Value "NULL" is not supported as a parameter',
            opendir(__DIR__),
        ];

        yield [
            'Value "(object) array(
)" is not supported as a parameter',
            new stdClass(),
        ];

        yield [
            "Value \"Safe\DateTime::__set_state(array(
   'date' => '2022-02-02 13:31:37.593289',
   'timezone_type' => 3,
   'timezone' => 'UTC',
))\" is not supported as a parameter",
            new DateTime('2022-02-02 13:31:37.593289'),
        ];
    }
}
