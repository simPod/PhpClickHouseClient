<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use InvalidArgumentException;
use function gettype;
use function Safe\sprintf;

final class UnsupportedValueType extends InvalidArgumentException implements ClickHouseClientException
{
    /**
     * @param mixed $value
     */
    public static function value($value) : self
    {
        return new self(sprintf('Value of type "%s" is not supported as a parameter', gettype($value)));
    }
}
