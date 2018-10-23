<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Output;

use SimPod\ClickHouseClient\Output\JsonCompact;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Output\JsonCompact */
final class JsonCompactTest extends TestCaseBase
{
    public function testPropertiesAreSet() : void
    {
        $format = new JsonCompact(
            <<<JSON
{
	"meta":
	[
		{
			"name": "number",
			"type": "UInt64"
		}
	],

	"data":
	[
		["0"],
		["1"]
	],

	"rows": 2,

	"rows_before_limit_at_least": 2,

	"statistics":
	{
		"elapsed": 0.0000504,
		"rows_read": 2,
		"bytes_read": 16
	}
}

JSON
        );

        self::assertSame(2, $format->rows);
        self::assertSame(2, $format->rowsBeforeLimitAtLeast);
        self::assertSame(
            [
                [
                    'name' => 'number',
                    'type' => 'UInt64',
                ],
            ],
            $format->meta
        );
        self::assertSame([['0'], ['1']], $format->data);
        self::assertSame(
            [
                'elapsed' => 5.04E-5,
                'rows_read' => 2,
                'bytes_read' => 16,
            ],
            $format->statistics
        );
    }
}
