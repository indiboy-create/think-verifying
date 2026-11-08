<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Youvision\ThinkVerifying\Service;
use Youvision\ThinkVerifying\Verifying;

class ServiceTest extends TestCase
{
    public function testRegisterMergesConfigAndBindsManager(): void
    {
        $app = new App(dirname(__DIR__));

        $app->config->set([
            'expire' => 999,
            'cache' => [
                'prefix' => 'custom',
            ],
            'scenes' => [
                'login' => [
                    'expire' => 600,
                ],
            ],
        ], 'verifying');

        $service = new Service($app);
        $service->register();

        $manager = $app->make('verifying');

        $this->assertInstanceOf(Verifying::class, $manager);
        $this->assertSame($manager, $app->make(Verifying::class));
        $this->assertSame(999, $manager->getConfig('expire'));
        $this->assertSame('custom', $manager->getConfig('cache')['prefix']);
        $this->assertSame(6, $manager->getConfig('code')['length']);
        $this->assertSame(600, $manager->getConfig('scenes')['login']['expire']);
    }
}