<?php

declare(strict_types=1);

namespace HZEX\Think\Cors;

use think\App;
use function array_map;
use function count;
use function implode;
use function in_array;
use function is_array;
use function rtrim;
use function array_key_first;

class CorsConfig
{
    /**
     * 允许访问的来源
     * @var string[]|true
     */
    protected $allowedOrigins;

    /**
     * 允许访问的来源匹配正则
     * @var string[]
     */
    protected $allowedOriginsPatterns;

    /**
     * 允许的请求方法
     * @var string[]|true
     */
    protected $allowedMethods;

    /**
     * 允许的请求头
     * @var string[]|true
     */
    protected $allowedHeaders;

    /**
     * 导出的协议头
     * @var string[]
     */
    protected $exposedHeaders;

    /**
     * 支持身份凭证
     * @var bool
     */
    protected $supportsCredentials = true;

    /**
     * 预验证缓存时间
     * @var int|null
     */
    protected $maxAge = 0;

    /**
     * @return CorsConfig
     */
    public static function getConfigFromContainer(): CorsConfig
    {
        return App::getInstance()->get(CorsConfig::class);
    }

    /**
     * @param array<string, mixed> $conf
     * @return self
     */
    public static function fromArray(array $conf): self
    {
        return (new self())
            ->setAllowedOrigins($conf['allowed_origins'] ?? [])
            ->setAllowedOriginsPatterns($conf['allowed_origins_patterns'] ?? [])
            ->setAllowedMethods($conf['allowed_methods'] ?? [])
            ->setAllowedHeaders($conf['allowed_headers'])
            ->setExposedHeaders($conf['exposed_headers'])
            ->setSupportsCredentials($conf['supports_credentials'] ?? false)
            ->setMaxAge($conf['max_age'] ?? null);
    }

    /**
     * @return string[]|true
     */
    public function getAllowedOrigins()
    {
        return $this->allowedOrigins;
    }

    /**
     * @return string
     */
    public function getAllowedOriginsFirst(): string
    {
        return $this->allowedOrigins[array_key_first($this->allowedOrigins)];
    }

    /**
     * @param string[]|true $allowedOrigins
     * @return self
     */
    public function setAllowedOrigins($allowedOrigins): self
    {
        $allowedOrigins = in_array('*', $allowedOrigins) ? true : $allowedOrigins;
        if (is_array($allowedOrigins)) {
            foreach ($allowedOrigins as $allowedOrigin) {
                $allowedOrigin = rtrim($allowedOrigin, '/');
                if (str_starts_with($allowedOrigin, '//')) {
                    $this->allowedOrigins[] = "http:{$allowedOrigin}";
                    $this->allowedOrigins[] = "https:{$allowedOrigin}";
                    continue;
                } elseif (!str_starts_with($allowedOrigin, 'http')) {
                    $this->allowedOrigins[] = "http://{$allowedOrigin}";
                    $this->allowedOrigins[] = "https://{$allowedOrigin}";
                    continue;
                }
                $this->allowedOrigins[] = $allowedOrigin;
            }
        } else {
            $this->allowedOrigins = $allowedOrigins;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isSingleOriginAllowed(): bool
    {
        if ($this->allowedOrigins === true || !empty($this->allowedOriginsPatterns)) {
            return false;
        }

        return count($this->allowedOrigins) === 1;
    }

    /**
     * @return string[]
     */
    public function getAllowedOriginsPatterns(): array
    {
        return $this->allowedOriginsPatterns;
    }

    /**
     * @param string[] $allowedOriginsPatterns
     * @return self
     */
    public function setAllowedOriginsPatterns(array $allowedOriginsPatterns): self
    {
        $this->allowedOriginsPatterns = $allowedOriginsPatterns;

        return $this;
    }

    /**
     * @return string[]|true
     */
    public function getAllowedMethods()
    {
        return $this->allowedMethods;
    }

    /**
     * @return string
     */
    public function getAllowedMethodsLine(): string
    {
        return implode(',', $this->allowedMethods);
    }

    /**
     * @param string[]|true $allowedMethods
     * @return self
     */
    public function setAllowedMethods($allowedMethods): self
    {
        $this->allowedMethods = in_array('*', $allowedMethods)
            ? true
            : array_map('\strtoupper', $allowedMethods);

        return $this;
    }

    /**
     * @return string[]|true
     */
    public function getAllowedHeaders()
    {
        return $this->allowedHeaders;
    }

    /**
     * @return string
     */
    public function getAllowedHeadersLine(): string
    {
        return implode(',', $this->allowedHeaders);
    }

    /**
     * @param string[]|true $allowedHeaders
     * @return self
     */
    public function setAllowedHeaders($allowedHeaders): self
    {
        $this->allowedHeaders = in_array('*', $allowedHeaders)
            ? true
            : array_map('\strtolower', $allowedHeaders);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getExposedHeaders(): array
    {
        return $this->exposedHeaders;
    }

    /**
     * @return string
     */
    public function getExposedHeadersLine(): string
    {
        return implode(', ', $this->exposedHeaders);
    }

    /**
     * @param string[] $exposedHeaders
     * @return self
     */
    public function setExposedHeaders(array $exposedHeaders): self
    {
        $this->exposedHeaders = $exposedHeaders;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSupportsCredentials(): bool
    {
        return $this->supportsCredentials;
    }

    /**
     * @param bool $supportsCredentials
     * @return self
     */
    public function setSupportsCredentials(bool $supportsCredentials): self
    {
        $this->supportsCredentials = $supportsCredentials;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    /**
     * @param int|null $maxAge
     * @return self
     */
    public function setMaxAge(?int $maxAge): self
    {
        $this->maxAge = $maxAge;

        return $this;
    }
}
