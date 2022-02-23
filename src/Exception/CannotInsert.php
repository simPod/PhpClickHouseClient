<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use Exception;

final class CannotInsert extends Exception implements ClickHouseClientException
{
    public static function noValues(): self
    {
        return new self();
    }
}
