<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying;

use think\Service as ThinkService;

/**
 * ThinkPHP 服务注册类。
 *
 * 安装后由 ThinkPHP 的 service:discover 自动发现，负责：
 *  - 合并组件默认配置与项目 config/verifying.php；
 *  - 将 Verifying 管理器注册到容器，供依赖注入、门面和 verifying() 助手使用。
 */
class Service extends ThinkService
{
    public function register(): void
    {
        $config = $this->mergeConfig();

        $this->app->config->set($config, 'verifying');

        $manager = new Verifying($config);

        $this->app->instance('verifying', $manager);
        $this->app->instance(Verifying::class, $manager);
    }

    public function boot(): void
    {
    }

    /**
     * 合并组件默认配置与项目配置，项目配置优先。
     */
    protected function mergeConfig(): array
    {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'verifying.php';
        $default = is_file($file) ? require $file : [];

        if (!is_array($default)) {
            $default = [];
        }

        $current = (array) $this->app->config->get('verifying', []);

        return array_replace_recursive($default, $current);
    }
}