<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use function Safe\json_decode;
use function Safe\sprintf;
use function str_replace;

final class JsonEachRow implements Output
{
    /** @var callable():void */
    private $wakeUp;

    /** @var bool */
    private $wokeUp;

    /** @var array<array<mixed>> */
    private $data;

    public function __construct(string $contentsJson)
    {
        $this->wakeUp = function () use ($contentsJson) : void {
            $contents   = json_decode(sprintf('[%s]', str_replace("}\n{", '},{', $contentsJson)), true);
            $this->data = $contents;
        };
    }

    /**
     * @return array<array<mixed>>
     */
    public function data() : array
    {
        if (! $this->wokeUp) {
            ($this->wakeUp)();
        }

        return $this->data;
    }
}
