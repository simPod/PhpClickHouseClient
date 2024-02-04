<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use JsonException;

use function json_decode;
use function sprintf;
use function str_replace;

use const JSON_THROW_ON_ERROR;

/**
 * @phpstan-immutable
 * @template T
 * @implements Output<T>
 */
final class JsonEachRow implements Output
{
    /** @var list<T> */
    public array $data;

    /** @throws JsonException */
    public function __construct(string $contentsJson)
    {
        /** @var list<T> $contents */
        $contents   = json_decode(
            sprintf('[%s]', str_replace("}\n{", '},{', $contentsJson)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->data = $contents;
    }
}
