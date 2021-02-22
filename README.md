# ThinkPHP 6.0 - CORS 跨域控制扩展

### 全局启用
  - 配置服务注册 (app/service.php)：``\HZEX\Think\Cors\Service::class``
### 局部启用
  1. 配置服务注册 (app/service.php)：``\HZEX\Think\Cors\Service::class``
  2. 禁用自动注册中间件：``config.cors.auto_register_middleware => false``
  3. 注册中间件：``\HZEX\Think\Cors\CorsMiddleware::class``
  4. 确保优先级：``config.middleware.priority``

## 代码引用
- [asm89/stack-cors](https://github.com/asm89/stack-cors)