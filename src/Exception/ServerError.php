<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use Exception;
use Psr\Http\Message\ResponseInterface;

final class ServerError extends Exception
{
    public static function fromResponse(ResponseInterface $response) : self
    {
        return new self((string) $response->getBody());
    }
}
