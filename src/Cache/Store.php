<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Cache;

/**
 * 验证码缓存契约。
 *
 * 默认实现为 ThinkCache（基于 ThinkPHP Cache 门面），
 * 测试或特殊环境可替换为数组、Redis 直连等自定义实现。
 */
interface Store
{
    /**
     * 读取缓存值。
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 写入缓存值。
     *
     * @param int $ttl 过期时间，单位秒；0 表示永久
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * 判断缓存键是否存在。
     */
    public function has(string $key): bool;

    /**
     * 删除缓存键。
     */
    public function delete(string $key): bool;

    /**
     * 在固定时间窗口内自增计数。
     *
     * @param int $step 每次增量
     * @param int $ttl  计数窗口，单位秒；0 表示永久
     *
     * @return int 自增后的计数值
     */
    public function increment(string $key, int $step = 1, int $ttl = 0): int;
}