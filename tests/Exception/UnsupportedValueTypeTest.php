<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Exception;

use Safe\DateTime;
use SimPod\ClickHouseClient\Exception\UnsupportedValueType;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use stdClass;
use function Safe\opendir;

/** @covers \SimPod\ClickHouseClient\Exception\UnsupportedValueType */
final class UnsupportedValueTypeTest extends TestCaseBase
{
    /**
     * @param mixed $value
     *
     * @dataProvider providerValue
     */
    public function testValue(string $expectedMessage, $value) : void
    {
        $exception = UnsupportedValueType::value($value);

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /** @return iterable<int, array{string, mixed}> */
    public function providerValue() : iterable
    {
        yield [
            'Value of type "resource" is not supported as a parameter',
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
}
