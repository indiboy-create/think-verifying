<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkVerifying\Limiter;
use Youvision\ThinkVerifying\Tests\Fixtures\ArrayStore;

class LimiterTest extends TestCase
{
    public function testLimit(): void
    {
        $limiter = new Limiter(new ArrayStore(), 0);

        $this->assertTrue($limiter->allow('send_account', 'login', '13800138000', 2, 3600));
        $this->assertTrue($limiter->allow('send_account', 'login', '13800138000', 2, 3600));
        $this->assertFalse($limiter->allow('send_account', 'login', '13800138000', 2, 3600));
    }

    public function testUnlimited(): void
    {
        $limiter = new Limiter(new ArrayStore(), 0);

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($limiter->allow('send_account', 'login', '13800138000', 0, 3600));
        }
    }

    public function testCooldown(): void
    {
        $limiter = new Limiter(new ArrayStore(), 60);

        $this->assertFalse($limiter->coolingDown('register', '13800138000'));

        $limiter->markCooldown('register', '13800138000');

        $this->assertTrue($limiter->coolingDown('register', '13800138000'));
        $this->assertFalse($limiter->coolingDown('login', '13800138000'));
    }
}