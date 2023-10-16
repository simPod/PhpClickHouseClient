<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Safe\Exceptions\JsonException;

use function Safe\json_decode;
use function sprintf;
use function str_replace;

/**
 * @psalm-immutable
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
        /**
         * @var list<T> $contents
         * @psalm-suppress ImpureFunctionCall
         */
        $contents   = json_decode(sprintf('[%s]', str_replace("}\n{", '},{', $contentsJson)), true);
        $this->data = $contents;
    }
}
