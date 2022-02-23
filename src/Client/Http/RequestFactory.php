<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function http_build_query;

use const PHP_QUERY_RFC3986;

final class RequestFactory
{
    public function __construct(private RequestFactoryInterface $requestFactory, private StreamFactoryInterface $streamFactory)
    {
    }

    public function prepareRequest(RequestOptions $requestOptions): RequestInterface
    {
        $query = http_build_query(
            $requestOptions->settings,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return $this->requestFactory->createRequest('POST', $query === '' ? '' : '?' . $query)
            ->withBody($this->streamFactory->createStream($requestOptions->sql));
    }
}
