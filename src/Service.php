<?php

declare(strict_types=1);

namespace Zxin\Think\Cors;

use think\App;

class Service extends \think\Service
{
    public function register()
    {
        $this->registerConfig();
        $this->registerMiddleware();
    }

    /**
     * 注册跨域配置
     */
    protected function registerConfig(): void
    {
        $this->app->bind(CorsConfig::class, function (App $app) {
            $conf = $app->config->get('cors', []);
            return CorsConfig::fromArray($conf);
        });
    }

    /**
     * 注册跨域中间件
     */
    protected function registerMiddleware(): void
    {
        if (!$this->app->config->get('cors.auto_register_middleware', true)) {
            return;
        }
        $this->app->middleware->unshift(CorsMiddleware::class, 'route');
    }
}
