<?php

declare(strict_types=1);

namespace HZEX\Think\Cors;

use think\Request;
use think\Response;
use function rtrim;
use function preg_match;
use function strtoupper;
use function in_array;
use function explode;

class CorsCore
{
    /**
     * @var CorsConfig
     */
    protected $config;

    /**
     * CorsService constructor.
     * @param CorsConfig $config
     */
    public function __construct(CorsConfig $config)
    {
        $this->config = $config;
    }

    /**
     * 是否存在请求来源
     * @param Request $request
     * @return bool
     */
    private function hasOrigin(Request $request): bool
    {
        return !empty($request->header('Origin'));
    }

    /**
     * 获取请求来源
     * @param Request $request
     * @return string
     */
    private function getOrigin(Request $request): string
    {
        $origin = $request->header('Origin');

        if (!$origin) {
            return '';
        }

        return rtrim($origin, '/');
    }

    private function getHost(Request $request): string
    {
        return "{$request->scheme()}://{$request->host()}";
    }

    /**
     * @param Response   $response
     * @param string     $name
     * @param string|int $value
     */
    private function setHeader(Response $response, string $name, $value): void
    {
        (function (string $name, string $value) {
            /** @noinspection PhpUndefinedFieldInspection */
            $this->header[$name] = $value;
        })->call($response, $name, $value);
    }

    /**
     * 是否一个Cors请求
     * @param Request $request
     * @return bool
     */
    public function isCorsRequest(Request $request): bool
    {
        return $this->hasOrigin($request);
    }

    /**
     * 是否预检请求
     * @param Request $request
     * @return bool
     */
    public function isPreflightRequest(Request $request): bool
    {
        // Sec-Fetch-Mode: cors
        return $request->method(true) === 'OPTIONS' && $request->header('Access-Control-Request-Method');
    }

    public function handlePreflightRequest(Request $request): Response
    {
        $response = Response::create('', 'html', 204);

        return $this->addPreflightRequestHeaders($response, $request);
    }

    public function addPreflightRequestHeaders(Response $response, Request $request): Response
    {
        $this->configureAllowedOrigin($response, $request);

        if ($response->getHeader('Access-Control-Allow-Origin')) {
            $this->configureAllowCredentials($response);

            $this->configureAllowedMethods($response, $request);

            $this->configureAllowedHeaders($response, $request);

            $this->configureMaxAge($response);
        }

        return $response;
    }

    public function addActualRequestHeaders(Response $response, Request $request): Response
    {
        $this->configureAllowedOrigin($response, $request);

        if ($response->getHeader('Access-Control-Allow-Origin')) {
            $this->configureAllowCredentials($response);

            $this->configureExposedHeaders($response);
        }

        return $response;
    }

    public function isOriginAllowed(Request $request): bool
    {
        if ($this->config->getAllowedOrigins() === true) {
            return true;
        }

        if (!$this->getOrigin($request)) {
            return false;
        }

        $origin = $this->getOrigin($request);

        if (in_array($origin, $this->config->getAllowedOrigins())) {
            return true;
        }

        foreach ($this->config->getAllowedOriginsPatterns() as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    private function configureAllowedOrigin(Response $response, Request $request)
    {
        if ($this->config->getAllowedOrigins() === true && !$this->config->isSupportsCredentials()) {
            // Safe+cacheable, allow everything
            $this->setHeader($response, 'Access-Control-Allow-Origin', '*');
        } elseif ($this->config->isSingleOriginAllowed()) {
            // Single origins can be safely set
            $this->setHeader(
                $response,
                'Access-Control-Allow-Origin',
                $this->config->getAllowedOriginsFirst()
            );
        } else {
            // For dynamic headers, set the requested Origin header when set and allowed
            if ($this->isCorsRequest($request) && $this->isOriginAllowed($request)) {
                $this->setHeader($response, 'Access-Control-Allow-Origin', $this->getOrigin($request));
            }

            $this->varyHeader($response, 'Origin');
        }
    }

    private function configureAllowedMethods(Response $response, Request $request)
    {
        if ($this->config->getAllowedMethods() === true) {
            $allowMethods = strtoupper($request->header('Access-Control-Request-Method'));
            $this->varyHeader($response, 'Access-Control-Request-Method');
        } else {
            $allowMethods = $this->config->getAllowedMethodsLine();
        }

        $this->setHeader($response, 'Access-Control-Allow-Methods', $allowMethods);
    }

    private function configureAllowedHeaders(Response $response, Request $request)
    {
        if ($this->config->getAllowedHeaders() === true) {
            $allowHeaders = $request->header('Access-Control-Request-Headers');
            $this->varyHeader($response, 'Access-Control-Request-Headers');
        } else {
            $allowHeaders = $this->config->getAllowedHeadersLine();
        }
        $this->setHeader($response, 'Access-Control-Allow-Headers', $allowHeaders);
    }

    private function configureAllowCredentials(Response $response)
    {
        if ($this->config->isSupportsCredentials()) {
            $this->setHeader($response, 'Access-Control-Allow-Credentials', 'true');
        }
    }

    private function configureExposedHeaders(Response $response)
    {
        if ($this->config->getExposedHeaders()) {
            $this->setHeader($response, 'Access-Control-Expose-Headers', $this->config->getExposedHeadersLine());
        }
    }

    private function configureMaxAge(Response $response)
    {
        if ($this->config->getMaxAge() !== null) {
            $this->setHeader($response, 'Access-Control-Max-Age', $this->config->getMaxAge());
        }
    }

    public function varyHeader(Response $response, $header): Response
    {
        if (!$response->getHeader('Vary')) {
            $this->setHeader($response, 'Vary', $header);
        } elseif (!in_array($header, explode(', ', $response->getHeader('Vary')))) {
            $this->setHeader($response, 'Vary', "{$response->getHeader('Vary')}, $header");
        }

        return $response;
    }

    /**
     * 检查Host是否一致
     * @param Request $request
     * @return bool
     */
    public function isSameHost(Request $request): bool
    {
        return $this->getOrigin($request) === $this->getHost($request);
    }
}
