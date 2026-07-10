<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Output;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Output\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function iterator_to_array;
use function str_repeat;

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

    public function testStreamLinesAreDecodedIndependently(): void
    {
        $format = new JsonEachRow(Utils::streamFor("{\"number\":\"0\"}\n{\"number\":\"1\"}"));

        self::assertSame(
            [['number' => '0'], ['number' => '1']],
            iterator_to_array($format->data, preserve_keys: false),
        );
    }

    public function testStreamLineCanSpanMultipleReads(): void
    {
        $value  = str_repeat('a', 9000);
        $format = new JsonEachRow(Utils::streamFor('{"value":"' . $value . '"}' . "\n" . '{"value":"b"}'));

        self::assertSame(
            [['value' => $value], ['value' => 'b']],
            iterator_to_array($format->data, preserve_keys: false),
        );
    }

    #[DataProvider('provideStreamedExceptionContents')]
    public function testStreamedExceptionFrameThrowsServerError(string|StreamInterface $contents): void
    {
        $format = new JsonEachRow($contents);

        $this->expectException(ServerError::class);
        $this->expectExceptionCode(60);
        $this->expectExceptionMessage('UNKNOWN_TABLE');

        iterator_to_array($format->data, preserve_keys: false);
    }

    /** @return iterable<array{string|StreamInterface}> */
    public static function provideStreamedExceptionContents(): iterable
    {
        $contents = <<<'JSON'
{"number":"0"}
__exception__
abcdefghijklmnop
Code: 60. DB::Exception: Table default.missing does not exist. (UNKNOWN_TABLE)
JSON;

        yield 'string' => [$contents];
        yield 'stream' => [Utils::streamFor($contents)];
    }
}
