<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Cache;

use think\facade\Cache as ThinkCacheFacade;

/**
 * 基于 ThinkPHP 缓存门面的默认实现。
 *
 * 计数使用“计数值 + 窗口到期时间”的结构保存在同一个缓存项中，
 * 每次自增后按剩余时间重新写入，避免部分缓存驱动 inc() 丢失过期时间。
 */
class ThinkCache implements Store
{
    /**
     * @param string|null $store  缓存驱动名，null 表示默认驱动
     * @param string      $prefix 所有缓存键的公共前缀
     */
    public function __construct(
        protected ?string $store = null,
        protected string $prefix = 'verifying'
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache()->get($this->key($key), $default);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return (bool) $this->cache()->set($this->key($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        return (bool) $this->cache()->has($this->key($key));
    }

    public function delete(string $key): bool
    {
        return (bool) $this->cache()->delete($this->key($key));
    }

    public function increment(string $key, int $step = 1, int $ttl = 0): int
    {
        $cache = $this->cache();
        $cacheKey = $this->key($key);
        $now = time();
        $entry = $cache->get($cacheKey);

        if (!is_array($entry) || !isset($entry['count'], $entry['expires_at']) || $entry['expires_at'] <= $now) {
            $entry = [
                'count' => 0,
                'expires_at' => $ttl > 0 ? $now + $ttl : 0,
            ];
        }

        $entry['count'] = (int) $entry['count'] + $step;
        $remaining = $entry['expires_at'] > 0 ? max(1, $entry['expires_at'] - $now) : 0;
        $cache->set($cacheKey, $entry, $remaining);

        return (int) $entry['count'];
    }

    /**
     * 获取 ThinkPHP 缓存驱动实例。
     */
    protected function cache(): mixed
    {
        return ThinkCacheFacade::store($this->store ?: '');
    }

    /**
     * 拼接带公共前缀的缓存键。
     */
    protected function key(string $key): string
    {
        return $this->prefix === '' ? $key : $this->prefix . ':' . $key;
    }
}