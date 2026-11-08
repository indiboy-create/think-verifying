<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Youvision\ThinkVerifying\Cache\ThinkCache;

class ThinkCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'verifying-cache-' . bin2hex(random_bytes(4)) . DIRECTORY_SEPARATOR;

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }

        $app = new App(dirname(__DIR__));
        $app->config->set([
            'default' => 'file',
            'stores' => [
                'file' => [
                    'type' => 'File',
                    'path' => $this->directory,
                ],
            ],
        ], 'cache');
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($this->directory);
    }

    public function testGetSetDelete(): void
    {
        $cache = new ThinkCache(null, 'test');

        $this->assertTrue($cache->set('foo', 'bar', 60));
        $this->assertTrue($cache->has('foo'));
        $this->assertSame('bar', $cache->get('foo'));
        $this->assertTrue($cache->delete('foo'));
        $this->assertFalse($cache->has('foo'));
        $this->assertSame('default', $cache->get('foo', 'default'));
    }

    public function testIncrement(): void
    {
        $cache = new ThinkCache(null, 'test');

        $this->assertSame(1, $cache->increment('count', 1, 60));
        $this->assertSame(2, $cache->increment('count', 1, 60));
        $this->assertSame(5, $cache->increment('count', 3, 60));
    }

    public function testPrefixIsolation(): void
    {
        $first = new ThinkCache(null, 'first');
        $second = new ThinkCache(null, 'second');

        $first->set('key', 'value', 60);

        $this->assertSame('value', $first->get('key'));
        $this->assertNull($second->get('key'));
    }
}