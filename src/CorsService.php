<?php
declare(strict_types=1);

namespace app\Service\Cors;

use think\Request;
use think\Response;
use function HZEX\Think\Cors\str_starts_with;

class CorsService
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
     * 是否一个Cors请求
     * @param Request $request
     * @return bool
     */
    public function isCorsRequest(Request $request)
    {
        return $this->hasOrigin($request) && !$this->isSameHost($request);
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

    /**
     * 检查请求源
     * @param Request $request
     * @return bool
     */
    public function checkOrigin(Request $request): bool
    {
        if ($this->allowedOrigins === true) {
            return true;
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

    /**
     * 检查请求方法
     * @param Request $request
     * @return bool
     */
    public function checkMethod(Request $request): bool
    {
        if ($this->allowedMethods === true) {
            return true;
        }

        $method = strtoupper($request->header('Access-Control-Request-Method', ''));

        return in_array($method, $this->allowedMethods);
    }

    /**
     * 是否允许来源请求
     * @param Request $request
     * @return bool
     */
    public function isRequestAllowed(Request $request)
    {
        return $this->checkOrigin($request);
    }

    /**
     * 是否预检请求
     * @param Request $request
     * @return bool
     */
    public function isPreflightRequest(Request $request): bool
    {
        // Sec-Fetch-Mode: cors
        return $this->isCorsRequest($request)
            && $request->method(true) === 'OPTIONS'
            && $request->header('Access-Control-Request-Method');
    }

    /**
     * 处理预检请求
     * @param Request $request
     * @return Response
     */
    public function handlePreflightRequest(Request $request): Response
    {
        if ($check = $this->checkPreflightRequestConditions($request)) {
            return $check;
        }

        return $this->buildPreflightResponse($request);
    }

    /**
     * 构建预检响应
     * @param Request $request
     * @return Response
     */
    public function buildPreflightResponse(Request $request): Response
    {
        $response = Response::create('', 'html', 204);

        $headers = [
            'Access-Control-Allow-Origin' => $this->getOrigin($request),
        ];

        if ($this->supportsCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if ($this->maxAge) {
            $headers['Access-Control-Max-Age'] = $this->maxAge;
        }

        $headers['Access-Control-Allow-Methods'] = $this->allowedMethods === true
            ? strtoupper($request->header('Access-Control-Request-Method', ''))
            : implode(', ', $this->allowedMethods);

        $headers['Access-Control-Allow-Headers'] = $this->allowedHeaders === true
            ? strtoupper($request->header('Access-Control-Request-Headers'))
            : implode(', ', $this->allowedHeaders);

        $response->header($headers);

        return $response;
    }

    /**
     * 检查预检请求
     * @param Request $request
     * @return Response|null
     */
    public function checkPreflightRequestConditions(Request $request): ?Response
    {
        if (!$this->checkOrigin($request)) {
            return $this->createBadRequestResponse(403, 'Origin not allowed');
        }

        if (!$this->checkMethod($request)) {
            return $this->createBadRequestResponse(405, 'Method not allowed');
        }

        if ($this->allowedHeaders && $headers = $request->header('Access-Control-Request-Headers')) {
            $headers = array_filter(explode(',', strtolower($headers)));

            foreach ($headers as $header) {
                if (!in_array(trim($header), $this->allowedHeaders)) {
                    return $this->createBadRequestResponse(403, 'Header not allowed');
                }
            }
        }

        return null;
    }

    /**
     * @param Response $response
     * @param Request  $request
     * @return Response
     */
    public function addRequestHeaders(Response $response, Request $request): Response
    {
        $headers = [
            'Access-Control-Allow-Origin' => $this->getOrigin($request),
        ];

        if ($vary = $response->getHeader('Vary')) {
            $headers['Vary'] = "{$vary}, Origin";
        } else {
            $headers['Vary'] = 'Origin';
        }

        if ($this->supportsCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if ($this->exposedHeaders) {
            $exposedHeaders = array_uintersect(
                $this->exposedHeaders,
                array_keys($response->getHeader()),
                '\strcasecmp'
            );
            if ($exposedHeaders) {
                $headers['Access-Control-Expose-Headers'] = implode(', ', $exposedHeaders);
            }
        }

        $response->header($headers);

        return $response;
    }

    private function createBadRequestResponse(int $code, string $reason = ''): Response
    {
        return Response::create($reason, 'html', $code);
    }
}
