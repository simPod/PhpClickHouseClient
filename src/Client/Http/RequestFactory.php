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
use SimPod\ClickHouseClient\Sql\Type;

use function array_keys;
use function array_reduce;
use function http_build_query;
use function is_string;
use function preg_match_all;
use function SimPod\ClickHouseClient\absurd;

use const PHP_QUERY_RFC3986;

final class RequestFactory
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

    /** @throws UnsupportedParamType */
    public function prepareRequest(RequestOptions $requestOptions): RequestInterface
    {
        $query = http_build_query(
            $requestOptions->settings,
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

        $request = $this->requestFactory->createRequest('POST', $uri);

        preg_match_all('~\{([a-zA-Z\d]+):([a-zA-Z\d ]+(\(.+\))?)}~', $requestOptions->sql, $matches);
        if ($matches[0] === []) {
            $body = $this->streamFactory->createStream($requestOptions->sql);
            try {
                return $request->withBody($body);
            } catch (InvalidArgumentException) {
                absurd();
            }
        }

        $typeToParam = array_reduce(
            array_keys($matches[1]),
            static function (array $acc, string|int $k) use ($matches) {
                $acc[$matches[1][$k]] = Type::fromString($matches[2][$k]);

                return $acc;
            },
            [],
        );

        $streamElements = [['name' => 'query', 'contents' => $requestOptions->sql]];
        foreach ($requestOptions->params as $name => $value) {
            $type = $typeToParam[$name] ?? null;
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
