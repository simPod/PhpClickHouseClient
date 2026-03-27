<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use Exception;
use Psr\Http\Message\ResponseInterface;

use function preg_match;

final class ServerError extends Exception
{
    private function __construct(
        string $message,
        int $code,
        public readonly int $httpStatusCode,
        public readonly string|null $clickHouseExceptionName,
    ) {
        parent::__construct($message, $code);
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $bodyContent = $response->getBody()->__toString();

        $errorCode = preg_match('~^Code: (\d+). DB::Exception:~', $bodyContent, $codeMatches) === 1
            ? (int) $codeMatches[1]
            : 0;

        $exceptionName = preg_match('~\(([A-Z][A-Z_\d]+)\)~', $bodyContent, $nameMatches) === 1
            ? $nameMatches[1]
            : null;

        return new self(
            $bodyContent,
            $errorCode,
            $response->getStatusCode(),
            $exceptionName,
        );
    }
}
