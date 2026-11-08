<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests\Fixtures;

use Youvision\ThinkVerifying\Cache\Store;

/**
 * 测试用内存缓存。
 */
class ArrayStore implements Store
{
    /**
     * @var array<string, array{value: mixed, expires_at: int}>
     */
    private array $items = [];

    /**
     * 为 true 时 set() 返回失败，用于测试缓存写入异常。
     */
    public bool $failOnSet = false;

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->items)) {
            return $default;
        }

        $item = $this->items[$key];

        if ($item['expires_at'] > 0 && $item['expires_at'] <= time()) {
            unset($this->items[$key]);

            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($this->failOnSet) {
            return false;
        }

        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ];

        return true;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->items)) {
            return false;
        }

        if ($this->items[$key]['expires_at'] > 0 && $this->items[$key]['expires_at'] <= time()) {
            unset($this->items[$key]);

            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function increment(string $key, int $step = 1, int $ttl = 0): int
    {
        $now = time();
        $entry = $this->get($key);

        if (!is_array($entry) || !isset($entry['count'], $entry['expires_at']) || ($entry['expires_at'] > 0 && $entry['expires_at'] <= $now)) {
            $entry = [
                'count' => 0,
                'expires_at' => $ttl > 0 ? $now + $ttl : 0,
            ];
        }

        $entry['count'] = (int) $entry['count'] + $step;
        $this->items[$key] = [
            'value' => $entry,
            'expires_at' => (int) $entry['expires_at'],
        ];

        return (int) $entry['count'];
    }
}