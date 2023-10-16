<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

use function http_build_query;
use function is_string;

use const PHP_QUERY_RFC3986;

final class RequestFactory
{
    private UriInterface|null $uri;

    /** @throws InvalidArgumentException */
    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        UriFactoryInterface|null $uriFactory = null,
        UriInterface|string $uri = '',
    ) {
        if ($uriFactory === null && $uri === '') {
            $uri = null;
        } elseif (is_string($uri)) {
            if ($uriFactory === null) {
                throw new InvalidArgumentException('UriFactoryInterface is required when `$uri` is string');
            }

            $uri = $uriFactory->createUri($uri);
        }

        $this->uri = $uri;
    }

    public function prepareRequest(RequestOptions $requestOptions): RequestInterface
    {
        $query = http_build_query(
            $requestOptions->settings,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        $body = $this->streamFactory->createStream($requestOptions->sql);

        if ($this->uri === null) {
            $uri = $query === '' ? '' : '?' . $query;
        } else {
            $uriQuery = $this->uri->getQuery();
            try {
                $uri = $this->uri->withQuery($uriQuery . ($uriQuery !== '' && $query !== '' ? '&' : '') . $query);
            } catch (InvalidArgumentException) {
                $this->absurd();
            }
        }

        $request = $this->requestFactory->createRequest('POST', $uri);
        try {
            $request = $request->withBody($body);
        } catch (InvalidArgumentException) {
            $this->absurd();
        }

        return $request;
    }

    /** @psalm-return never */
    private function absurd(): void
    {
        throw new RuntimeException('Called `absurd` function which should never be called');
    }
}
