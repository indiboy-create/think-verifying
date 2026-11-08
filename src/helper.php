<?php

use Youvision\ThinkVerifying\Verifying;

if (!function_exists('verifying')) {
    /**
     * 获取验证码管理器，或直接切换到指定场景。
     *
     * 示例：
     *   verifying()->generate('13800138000');
     *   verifying('register')->generate('13800138000');
     */
    function verifying(?string $scene = null): Verifying
    {
        if (!function_exists('app')) {
            throw new \RuntimeException('think-verifying 需要运行在 ThinkPHP 环境中');
        }

        try {
            $manager = app('verifying');
        } catch (\Throwable $e) {
            throw new \RuntimeException('think-verifying 服务未注册，请确认已执行 service:discover', 0, $e);
        }

        if (!$manager instanceof Verifying) {
            throw new \RuntimeException('think-verifying 服务未注册，请确认已执行 service:discover');
        }

        return $scene === null || $scene === '' ? $manager : $manager->scene($scene);
    }
}

if (!function_exists('verifying_env')) {
    /**
     * 读取环境变量，兼容未安装 ThinkPHP 框架时直接加载配置文件的场景。
     */
    function verifying_env(string $key, mixed $default = null): mixed
    {
        if (function_exists('env')) {
            return env($key, $default);
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}