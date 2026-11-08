<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Youvision\ThinkVerifying\Service;
use Youvision\ThinkVerifying\Verifying;

class IntegrationTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'verifying-integration-cache-' . bin2hex(random_bytes(4)) . DIRECTORY_SEPARATOR;

        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($this->cacheDirectory);
    }

    public function testEndToEndGenerationAndVerificationWithRealCache(): void
    {
        $app = new App(dirname(__DIR__));

        $app->config->set([
            'default' => 'file',
            'stores' => [
                'file' => [
                    'type' => 'File',
                    'path' => $this->cacheDirectory,
                ],
            ],
        ], 'cache');

        $app->config->set([
            'secret' => 'integration-secret',
            'resend_interval' => 0,
            'max_attempts' => 2,
            'limit' => [
                'account_count' => 5,
                'account_window' => 3600,
                'ip_count' => 5,
                'ip_window' => 3600,
            ],
        ], 'verifying');

        $service = new Service($app);
        $service->register();

        /** @var Verifying $verifying */
        $verifying = $app->make('verifying');

        $result = $verifying->generate('13800138000');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(6, strlen((string) $result->get('code')));
        $this->assertSame(32, strlen($result->token()));

        $token = $result->token();
        $code = (string) $result->get('code');

        $this->assertTrue($verifying->check($token, $code, '13800138000')->isSuccess());
        $this->assertFalse($verifying->check($token, $code, '13800138000')->isSuccess());
    }
}