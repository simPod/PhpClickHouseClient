<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Format;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function iterator_to_array;

#[CoversClass(JsonEachRow::class)]
final class JsonEachRowTest extends TestCaseBase
{
    public function testOutputAcceptsStream(): void
    {
        /** @var JsonEachRow<array{number: string}> $format */
        $format = new JsonEachRow();

        $output = $format::output(Utils::streamFor("{\"number\":\"0\"}\n{\"number\":\"1\"}"));

        self::assertSame(
            [['number' => '0'], ['number' => '1']],
            iterator_to_array($output->data, preserve_keys: false),
        );
    }
}
