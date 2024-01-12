<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use Throwable;

use function uniqid;

final class LoggerPlugin implements Plugin
{
    public function __construct(private SqlLogger $logger)
    {
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $id = uniqid('', true);
        $this->logger->startQuery($id, (string) $request->getBody());

        return $next($request)->then(
            function (ResponseInterface $response) use ($id): ResponseInterface {
                $this->logger->stopQuery($id);

                return $response;
            },
            function (Throwable $throwable) use ($id): void {
                $this->logger->stopQuery($id);

                throw $throwable;
            },
        );
    }
}
