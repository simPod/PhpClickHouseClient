<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

use function http_build_query;
use function is_string;

use const PHP_QUERY_RFC3986;

final class RequestFactory
{
    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private UriFactoryInterface|null $uriFactory = null,
        private UriInterface|string $uri = '',
    ) {
    }

    public function prepareRequest(RequestOptions $requestOptions): RequestInterface
    {
        $query = http_build_query(
            $requestOptions->settings,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        if ($this->uriFactory === null) {
            return $this->requestFactory->createRequest('POST', $query === '' ? '' : '?' . $query)
                ->withBody($this->streamFactory->createStream($requestOptions->sql));
        }

        $uri = $this->uri;
        if (is_string($uri)) {
            $uri = $this->uriFactory->createUri($uri);
        }

        $uriQuery = $uri->getQuery();
        $uri      = $uri->withQuery($uriQuery . ($uriQuery !== '' && $query !== '' ? '&' : '') . $query);

        return $this->requestFactory->createRequest('POST', $uri)
            ->withBody($this->streamFactory->createStream($requestOptions->sql));
    }
}
