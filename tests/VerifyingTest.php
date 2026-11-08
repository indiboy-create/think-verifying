<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkVerifying\Tests\Fixtures\ArrayStore;
use Youvision\ThinkVerifying\Verifying;

class VerifyingTest extends TestCase
{
    private ArrayStore $store;

    private Verifying $verifying;

    protected function setUp(): void
    {
        $this->store = new ArrayStore();
        $this->verifying = $this->make();
    }

    public function testSceneReturnsIndependentInstance(): void
    {
        $login = $this->verifying->scene('login');

        $this->assertSame('login', $login->sceneName());
        $this->assertSame('default', $this->verifying->sceneName());
    }

    public function testGenerateReturnsCodeAndToken(): void
    {
        $result = $this->verifying->generate('13800138000');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('default', $result->get('scene'));
        $this->assertSame(300, $result->expire());
        $this->assertSame('5分钟', $result->get('expire_text'));
        $this->assertSame(32, strlen($result->token()));

        $code = (string) $result->get('code');

        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));
        $this->assertTrue($this->verifying->verify($result->token(), $code, '13800138000'));
    }

    public function testCodeCanOnlyBeConsumedOnce(): void
    {
        $result = $this->verifying->generate('13800138000');
        $token = $result->token();
        $code = (string) $result->get('code');

        $first = $this->verifying->check($token, $code, '13800138000');
        $second = $this->verifying->check($token, $code, '13800138000');

        $this->assertTrue($first->isSuccess());
        $this->assertFalse($second->isSuccess());
        $this->assertFalse($this->verifying->verify($token, $code, '13800138000'));
    }

    public function testCodeCannotBeUsedInAnotherScene(): void
    {
        $result = $this->verifying->scene('login')->generate('13800138000');
        $token = $result->token();
        $code = (string) $result->get('code');

        $wrongScene = $this->verifying->scene('register')->check($token, $code, '13800138000');
        $rightScene = $this->verifying->scene('login')->check($token, $code, '13800138000');

        $this->assertFalse($wrongScene->isSuccess());
        $this->assertTrue($rightScene->isSuccess());
    }

    public function testCodeCannotBeUsedWithAnotherAccount(): void
    {
        $result = $this->verifying->scene('login')->generate('13800138000');
        $token = $result->token();
        $code = (string) $result->get('code');

        $wrongAccount = $this->verifying->scene('login')->check($token, $code, '13900139000');
        $rightAccount = $this->verifying->scene('login')->check($token, $code, '13800138000');

        $this->assertFalse($wrongAccount->isSuccess());
        $this->assertTrue($rightAccount->isSuccess());
    }

    public function testMaxAttemptsInvalidatesCode(): void
    {
        $result = $this->verifying->scene('login')->generate('13800138000');
        $token = $result->token();
        $code = (string) $result->get('code');

        for ($i = 0; $i < 2; $i++) {
            $this->assertFalse($this->verifying->scene('login')->check($token, '000000', '13800138000')->isSuccess());
        }

        $this->assertFalse($this->verifying->scene('login')->check($token, $code, '13800138000')->isSuccess());
    }

    public function testCooldown(): void
    {
        $first = $this->verifying->generate('13800138000');
        $second = $this->verifying->generate('13800138000');

        $this->assertTrue($first->isSuccess());
        $this->assertFalse($second->isSuccess());
        $this->assertSame(429, $second->getCode());
        $this->assertStringContainsString('生成过于频繁', $second->getMessage());
    }

    public function testSceneOverridesGlobalSettings(): void
    {
        $result = $this->verifying->scene('login')->generate('13800138000');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(600, $result->expire());
        $this->assertSame('10分钟', $result->get('expire_text'));

        $code = (string) $result->get('code');

        $this->assertSame(4, strlen($code));
        $this->assertStringNotContainsString('0', $code);
        $this->assertTrue(ctype_digit($code));
    }

    public function testAccountLimit(): void
    {
        $verifying = $this->make([
            'resend_interval' => 0,
            'limit' => [
                'account_count' => 2,
                'account_window' => 3600,
                'ip_count' => 100,
                'ip_window' => 3600,
            ],
        ]);

        $first = $verifying->generate('13800138000');
        $second = $verifying->generate('13800138000');
        $third = $verifying->generate('13800138000');

        $this->assertTrue($first->isSuccess());
        $this->assertTrue($second->isSuccess());
        $this->assertFalse($third->isSuccess());
        $this->assertSame(429, $third->getCode());
        $this->assertStringContainsString('账号生成次数已达上限', $third->getMessage());
    }

    public function testEmptyAccountRejected(): void
    {
        $result = $this->verifying->generate('');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(400, $result->getCode());
    }

    public function testInvalidCodeTypeRejected(): void
    {
        $verifying = $this->make([
            'code' => [
                'length' => 6,
                'type' => 'unknown',
            ],
        ]);

        $result = $verifying->generate('13800138000');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(400, $result->getCode());
    }

    public function testCacheFailure(): void
    {
        $this->store->failOnSet = true;

        $result = $this->verifying->generate('13800138000');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(500, $result->getCode());
        $this->assertStringContainsString('验证码生成失败', $result->getMessage());
    }

    public function testForgetCode(): void
    {
        $result = $this->verifying->scene('login')->generate('13800138000');
        $token = $result->token();
        $code = (string) $result->get('code');

        $this->assertTrue($this->verifying->scene('login')->forget($token));
        $this->assertFalse($this->verifying->scene('login')->check($token, $code, '13800138000')->isSuccess());
    }

    public function testMakeCode(): void
    {
        $code = $this->verifying->makeCode(4, 'numeric');

        $this->assertSame(4, strlen($code));
        $this->assertTrue(ctype_digit($code));
    }

    public function testUnknownSceneUsesGlobalSettings(): void
    {
        $result = $this->verifying->generate('13800138000', 'unknown');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(300, $result->expire());
    }

    public function testGetConfig(): void
    {
        $this->assertSame(300, $this->verifying->getConfig('expire'));
        $this->assertSame('fallback', $this->verifying->getConfig('missing', 'fallback'));
        $this->assertIsArray($this->verifying->getConfig());
    }

    /**
     * @param array<string, mixed> $override
     */
    private function make(array $override = []): Verifying
    {
        return new Verifying(
            $this->config($override),
            $this->store,
            '127.0.0.1'
        );
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function config(array $override = []): array
    {
        $config = [
            'default_scene' => 'default',
            'cache' => [
                'store' => null,
                'prefix' => 'test',
            ],
            'secret' => 'test-secret',
            'code' => [
                'length' => 6,
                'type' => 'numeric',
            ],
            'expire' => 300,
            'resend_interval' => 60,
            'max_attempts' => 3,
            'limit' => [
                'account_count' => 2,
                'account_window' => 3600,
                'ip_count' => 100,
                'ip_window' => 3600,
            ],
            'scenes' => [
                'default' => [],
                'register' => [],
                'login' => [
                    'expire' => 600,
                    'resend_interval' => 0,
                    'max_attempts' => 2,
                    'code' => [
                        'length' => 4,
                        'type' => 'nozero',
                    ],
                ],
                'reset_password' => [
                    'expire' => 900,
                ],
            ],
        ];

        return array_replace_recursive($config, $override);
    }
}