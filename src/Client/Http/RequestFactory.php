<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use GuzzleHttp\Psr7\MultipartStream;
use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Sql\NativeParameterParser;

use function http_build_query;
use function is_string;
use function SimPod\ClickHouseClient\absurd;

use const PHP_QUERY_RFC3986;

final readonly class RequestFactory
{
    private UriInterface|null $uri;

    /** @throws InvalidArgumentException */
    public function __construct(
        private ParamValueConverterRegistry $paramValueConverterRegistry,
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

    /** @param array<string, mixed> $additionalOptions */
    public function initRequest(
        RequestSettings $requestSettings,
        array $additionalOptions = [],
    ): RequestInterface {
        $query = http_build_query(
            $requestSettings->settings + $additionalOptions,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        if ($this->uri === null) {
            $uri = $query === '' ? '' : '?' . $query;
        } else {
            $uriQuery = $this->uri->getQuery();
            try {
                $uri = $this->uri->withQuery($uriQuery . ($uriQuery !== '' && $query !== '' ? '&' : '') . $query);
            } catch (InvalidArgumentException) {
                absurd();
            }
        }

        return $this->requestFactory->createRequest('POST', $uri);
    }

    /** @throws UnsupportedParamType */
    public function prepareSqlRequest(
        string $sql,
        RequestSettings $requestSettings,
        RequestOptions $requestOptions,
    ): RequestInterface {
        $request = $this->initRequest($requestSettings);

        $paramToType = NativeParameterParser::parse($sql);
        if ($paramToType === []) {
            $body = $this->streamFactory->createStream($sql);
            try {
                return $request->withBody($body);
            } catch (InvalidArgumentException) {
                absurd();
            }
        }

        $streamElements = [['name' => 'query', 'contents' => $sql]];
        foreach ($requestOptions->params as $name => $value) {
            $type = $paramToType[$name] ?? null;
            if ($type === null) {
                continue;
            }

            $streamElements[] = [
                'name' => 'param_' . $name,
                'contents' => $this->paramValueConverterRegistry->get($type)($value, $type, false),
            ];
        }

        try {
            $body    = new MultipartStream($streamElements);
            $request = $request->withBody($body)
                ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $body->getBoundary());
        } catch (InvalidArgumentException) {
            absurd();
        }

        return $request;
    }
}
