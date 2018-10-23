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
    /** @var RequestFactoryInterface */
    private $requestFactory;

    /** @var UriFactoryInterface */
    private $uriFactory;

    /** @var StreamFactoryInterface */
    private $streamFactory;

    public function __construct(
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        UriFactoryInterface $uriFactory
    ) {
        $this->requestFactory = $requestFactory;
        $this->uriFactory     = $uriFactory;
        $this->streamFactory  = $streamFactory;
    }

    public function prepareRequest(string $endpoint, RequestOptions $requestOptions) : RequestInterface
    {
        $uri = $this->uriFactory->createUri($endpoint);
        $uri = $uri->withQuery(
            http_build_query(
                $requestOptions->parameters,
                '',
                '&',
                PHP_QUERY_RFC3986
            )
        );

        $body = $this->streamFactory->createStream($requestOptions->sql);

        $request = $this->requestFactory->createRequest('POST', $uri);

        $request = $request->withBody($body);

        return $request;
    }
}
