<?php

declare(strict_types=1);

namespace HZEX\Think\Cors;

class Service extends \think\Service
{
    public function register()
    {
        // 注册跨域中间件
        $this->app->middleware->unshift(CorsMiddleware::class, 'route');
    }
}
