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
     * @var CorsCore
     */
    protected $cors;

    public function __construct(Config $config)
    {
        $conf = $config->get('cros', []);

        $this->cors = new CorsCore(
            $conf['allowed_origins'] ?? [],
            $conf['allowed_origins_patterns'] ?? [],
            $conf['allowed_methods'] ?? [],
            $conf['allowed_headers'] ?? [],
            $conf['exposed_headers'] ?? [],
            $conf['supports_credentials'] ?? false,
            $conf['max_age'] ?? 0
        );
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
        if ($this->cors->isPreflightRequest($request)) {
            return $this->cors->handlePreflightRequest($request);
        }

        if (!$this->cors->isRequestAllowed($request)) {
            return Response::create('Not allowed in CORS policy.', 'html', 403);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($this->cors->isCorsRequest($request)) {
            $this->cors->addRequestHeaders($response, $request);
        }

        return $response;
    }
}
