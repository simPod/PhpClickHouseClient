<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Format;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Format\Pretty;
use SimPod\ClickHouseClient\Format\PrettySpace;
use SimPod\ClickHouseClient\Format\RowBinary;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Output\Basic;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(Pretty::class)]
#[CoversClass(PrettySpace::class)]
#[CoversClass(RowBinary::class)]
#[CoversClass(TabSeparated::class)]
final class BasicFormatsTest extends TestCaseBase
{
    public function testPrettyOutputAcceptsStream(): void
    {
        $output = Pretty::output(Utils::streamFor('pretty'));

        self::assertInstanceOf(Basic::class, $output);
        self::assertSame('pretty', $output->contents);
    }

    public function testPrettySpaceOutputAcceptsStream(): void
    {
        $output = PrettySpace::output(Utils::streamFor('pretty-space'));

        self::assertInstanceOf(Basic::class, $output);
        self::assertSame('pretty-space', $output->contents);
    }

    public function testRowBinaryOutputAcceptsStream(): void
    {
        $output = RowBinary::output(Utils::streamFor('row-binary'));

        self::assertInstanceOf(Basic::class, $output);
        self::assertSame('row-binary', $output->contents);
    }

    public function testTabSeparatedOutputAcceptsStream(): void
    {
        $output = TabSeparated::output(Utils::streamFor("tab\tseparated"));

        self::assertInstanceOf(Basic::class, $output);
        self::assertSame("tab\tseparated", $output->contents);
    }
}
