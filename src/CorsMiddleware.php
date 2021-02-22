<?php

declare(strict_types=1);

namespace HZEX\Think\Cors;

use Closure;
use think\Config;
use think\Request;
use think\Response;

class CorsMiddleware
{
    /**
     * @var CorsConfig
     */
    protected $config;

    public function __construct(Config $config)
    {
        $conf = $config->get('cors', []);
        $this->config = CorsConfig::fromArray($conf);
    }

    /**
     * 允许跨域请求
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cors = new CorsCore($this->config);
        if ($cors->isPreflightRequest($request)) {
            $response = $cors->handlePreflightRequest($request);
            return $cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        /** @var Response $response */
        $response = $next($request);

        if ($request->method(true) === 'OPTIONS') {
            $cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        return $cors->addActualRequestHeaders($response, $request);
    }
}
