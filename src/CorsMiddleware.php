<?php

declare(strict_types=1);

namespace HZEX\Think\Cors;

use Closure;
use think\App;
use think\Request;
use think\Response;

class CorsMiddleware
{
    /**
     * 允许跨域请求
     * @access public
     * @param Request         $request
     * @param Closure         $next
     * @param CorsConfig|null $config
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?CorsConfig $config = null): Response
    {
        $config = $config ?? CorsConfig::getConfigFromContainer();
        $cors = new CorsCore($config);
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
