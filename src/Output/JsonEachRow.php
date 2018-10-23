<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use function Safe\json_decode;
use function Safe\sprintf;
use function str_replace;

/** @psalm-immutable */
final class JsonEachRow implements Output
{
    /** @var array<array<string, mixed>> */
    public $data;

    public function __construct(string $contentsJson)
    {
        /**
         * @var array<array<string, mixed>> $contents
         * @psalm-suppress ImpureFunctionCall
         */
        $contents   = json_decode(sprintf('[%s]', str_replace("}\n{", '},{', $contentsJson)), true);
        $this->data = $contents;
    }
}
