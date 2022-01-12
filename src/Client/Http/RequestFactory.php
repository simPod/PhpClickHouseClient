<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

use function http_build_query;

use const PHP_QUERY_RFC3986;

final class RequestFactory
{
    private RequestFactoryInterface $requestFactory;

    private UriFactoryInterface $uriFactory;

    private StreamFactoryInterface $streamFactory;

    public function __construct(RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory)
    {
        $this->requestFactory = $requestFactory;
        $this->streamFactory  = $streamFactory;
    }

    public function prepareRequest(RequestOptions $requestOptions) : RequestInterface
    {
        $query = http_build_query(
            $requestOptions->settings,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $request = $this->requestFactory->createRequest('POST', $query);

        $body    = $this->streamFactory->createStream($requestOptions->sql);
        $request = $request->withBody($body);

        return $request;
    }
}
