<?php
declare(strict_types=1);

namespace HZEX\Think\Cors;

use think\Request;
use think\Response;
use function str_starts_with;

class CorsCore
{
    /**
     * 允许访问的来源
     * @var array|true
     */
    protected $allowedOrigins;

    /**
     * 允许访问的来源匹配正则
     * @var array
     */
    protected $allowedOriginsPatterns;

    /**
     * 允许的请求方法
     * @var array|true
     */
    protected $allowedMethods;

    /**
     * 允许的请求头
     * @var array|true
     */
    protected $allowedHeaders;

    /**
     * 导出的协议头
     * @var array
     */
    protected $exposedHeaders;

    /**
     * 支持身份凭证
     * @var bool
     */
    protected $supportsCredentials = true;

    /**
     * 预验证缓存时间
     * @var int
     */
    protected $maxAge = 0;

    /**
     * CorsService constructor.
     * @param array $allowedOrigins
     * @param array $allowedOriginsPatterns
     * @param array $allowedMethods
     * @param array $allowedHeaders
     * @param array $exposedHeaders
     * @param bool  $supportsCredentials
     * @param int   $maxAge
     */
    public function __construct(
        array $allowedOrigins = [],
        array $allowedOriginsPatterns = [],
        array $allowedMethods = [],
        array $allowedHeaders = [],
        array $exposedHeaders = [],
        bool $supportsCredentials = false,
        int $maxAge = 0
    ) {
        $this->supportsCredentials = $supportsCredentials;

        $allowedOrigins = in_array('*', $allowedOrigins) ? true : $allowedOrigins;
        if (is_array($allowedOrigins)) {
            foreach ($allowedOrigins as $allowedOrigin) {
                $allowedOrigin = rtrim($allowedOrigin, '/');
                if (str_starts_with($allowedOrigin, '//')) {
                    $this->allowedOrigins[] = "http:{$allowedOrigin}";
                    $this->allowedOrigins[] = "https:{$allowedOrigin}";
                    continue;
                }
                if (!str_starts_with($allowedOrigin, 'http')) {
                    $this->allowedOrigins[] = "http://{$allowedOrigin}";
                    $this->allowedOrigins[] = "https://{$allowedOrigin}";
                    continue;
                }
                $this->allowedOrigins[] = $allowedOrigin;
            }
        } else {
            $this->allowedOrigins = $allowedOrigins;
        }

        $this->allowedOriginsPatterns = $allowedOriginsPatterns;

        $this->allowedMethods = in_array('*', $allowedMethods)
            ? true
            : array_map('\strtoupper', $allowedMethods);

        $this->allowedHeaders = in_array('*', $allowedHeaders)
            ? true
            : array_map('\strtolower', $allowedHeaders);

        $this->exposedHeaders = $exposedHeaders;

        $this->maxAge = $maxAge;
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
    public function isCorsRequest(Request $request)
    {
        return $this->hasOrigin($request) && !$this->isSameHost($request);
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
            $this->configureAllowCredentials($response, $request);

            $this->configureAllowedMethods($response, $request);

            $this->configureAllowedHeaders($response, $request);

            $this->configureMaxAge($response, $request);
        }

        return $response;
    }

    public function addActualRequestHeaders(Response $response, Request $request): Response
    {
        $this->configureAllowedOrigin($response, $request);

        if ($response->getHeader('Access-Control-Allow-Origin')) {
            $this->configureAllowCredentials($response, $request);

            $this->configureExposedHeaders($response, $request);
        }

        return $response;
    }

    public function isOriginAllowed(Request $request): bool
    {
        if ($this->allowedOrigins === true) {
            return true;
        }

        if (!$this->getOrigin($request)) {
            return false;
        }

        $origin = $this->getOrigin($request);

        if (in_array($origin, $this->allowedOrigins)) {
            return true;
        }

        foreach ($this->allowedOriginsPatterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    private function configureAllowedOrigin(Response $response, Request $request)
    {
        if ($this->allowedOrigins === true && !$this->supportsCredentials) {
            // Safe+cacheable, allow everything
            $this->setHeader($response, 'Access-Control-Allow-Origin', '*');
        } elseif ($this->isSingleOriginAllowed()) {
            // Single origins can be safely set
            $this->setHeader($response, 'Access-Control-Allow-Origin', array_values($this->allowedOrigins)[0]);
        } else {
            // For dynamic headers, check the origin first
            if ($this->isOriginAllowed($request)) {
                $this->setHeader($response, 'Access-Control-Allow-Origin', $this->getOrigin($request));
            }

            $this->varyHeader($response, 'Origin');
        }
    }

    private function isSingleOriginAllowed(): bool
    {
        if ($this->allowedOrigins === true || !empty($this->allowedOriginsPatterns)) {
            return false;
        }

        return count($this->allowedOrigins) === 1;
    }

    private function configureAllowedMethods(Response $response, Request $request)
    {
        if ($this->allowedMethods === true) {
            $allowMethods = strtoupper($request->header('Access-Control-Request-Method'));
            $this->varyHeader($response, 'Access-Control-Request-Method');
        } else {
            $allowMethods = implode(', ', $this->allowedMethods);
        }

        $this->setHeader($response, 'Access-Control-Allow-Methods', $allowMethods);
    }

    private function configureAllowedHeaders(Response $response, Request $request)
    {
        if ($this->allowedHeaders === true) {
            $allowHeaders = $request->header('Access-Control-Request-Headers');
            $this->varyHeader($response, 'Access-Control-Request-Headers');
        } else {
            $allowHeaders = implode(', ', $this->allowedHeaders);
        }
        $this->setHeader($response, 'Access-Control-Allow-Headers', $allowHeaders);
    }

    private function configureAllowCredentials(Response $response, Request $request)
    {
        if ($this->supportsCredentials) {
            $this->setHeader($response, 'Access-Control-Allow-Credentials', 'true');
        }
    }

    private function configureExposedHeaders(Response $response, Request $request)
    {
        if ($this->exposedHeaders) {
            $this->setHeader($response, 'Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        }
    }

    private function configureMaxAge(Response $response, Request $request)
    {
        if ($this->maxAge !== null) {
            $this->setHeader($response, 'Access-Control-Max-Age', $this->maxAge);
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
