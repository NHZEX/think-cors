<?php
declare(strict_types=1);

namespace HZEX\Think\Cors;

use app\Service\Cors\CorsMiddleware;

class Service extends \think\Service
{
    public function register()
    {
        if (!defined('CORS_NOT_AUTO_REGISTER')) {
            // 注册跨域中间件
            $this->app->middleware->unshift(CorsMiddleware::class, 'route');
        }
    }
}
