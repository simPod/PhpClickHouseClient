<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use Exception;
use Psr\Http\Message\ResponseInterface;

use function preg_match;

final class ServerError extends Exception
{
    public static function fromResponse(ResponseInterface $response): self
    {
        $bodyContent = $response->getBody()->__toString();

        return new self(
            $bodyContent,
            code: preg_match('~^Code: (\\d+). DB::Exception:~', $bodyContent, $matches) === 1 ? (int) $matches[1] : 0,
        );
    }
}
