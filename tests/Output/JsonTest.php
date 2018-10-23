<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Output;

use SimPod\ClickHouseClient\Output\Json;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Output\Json */
final class JsonTest extends TestCaseBase
{
    public function testPropertiesAreSet() : void
    {
        $format = new Json(
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
		{
            "number": "0"
		},
		{
            "number": "1"
		}
	],

	"rows": 2,

	"rows_before_limit_at_least": 2,

	"statistics":
	{
        "elapsed": 0.0000342,
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
        self::assertSame([['number' => '0'], ['number' => '1']], $format->data);
        self::assertSame(
            [
                'elapsed'    => 3.42E-5,
                'rows_read'  => 2,
                'bytes_read' => 16,
            ],
            $format->statistics
        );
    }
}
