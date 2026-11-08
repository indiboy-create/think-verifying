<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying;

use Youvision\ThinkVerifying\Cache\Store;
use Youvision\ThinkVerifying\Cache\ThinkCache;

/**
 * 验证码管理器。
 *
 * 只负责验证码的生成、缓存、token、场景隔离、频率限制与校验，
 * 不包含短信、邮件等发送实现。业务层拿到验证码后自行通过
 * youvision/think-easysms、youvision/think-mailer 或其它通道发送。
 *
 * 典型用法：
 *   $result = Verifying::scene('register')->generate('13800138000');
 *   $code   = $result->get('code');
 *   $token  = $result->token();
 *   $ok     = Verifying::scene('register')->verify($token, $code, '13800138000');
 */
class Verifying
{
    /**
     * 通用配置。
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * 缓存实现。
     */
    protected Store $store;

    /**
     * 固定客户端 IP，null 表示从 ThinkPHP 请求对象中获取。
     */
    protected ?string $ip;

    /**
     * 当前场景。
     */
    protected string $scene;

    /**
     * 验证码核心，延迟实例化。
     */
    protected ?Code $codeStore = null;

    /**
     * 频率限制器，延迟实例化。
     */
    protected ?Limiter $limiter = null;

    /**
     * @param array<string, mixed> $config 通用配置
     * @param Store|null           $store  自定义缓存实现，主要供测试使用
     * @param string|null          $ip     固定客户端 IP，主要供测试或队列任务使用
     */
    public function __construct(
        array $config = [],
        ?Store $store = null,
        ?string $ip = null
    ) {
        $this->config = array_replace_recursive($this->defaultConfig(), $config);
        $this->ip = $ip;
        $this->scene = (string) ($this->config['default_scene'] ?? 'default');

        $cache = (array) ($this->config['cache'] ?? []);
        $this->store = $store ?? new ThinkCache(
            isset($cache['store']) ? (string) $cache['store'] : null,
            (string) ($cache['prefix'] ?? 'verifying')
        );
    }

    /**
     * 设置当前场景，返回独立副本，避免并发调用时互相污染。
     */
    public function scene(string $scene): static
    {
        $clone = clone $this;
        $clone->scene = $scene !== '' ? $scene : (string) ($this->config['default_scene'] ?? 'default');

        return $clone;
    }

    /**
     * 获取当前场景名。
     */
    public function sceneName(): string
    {
        return $this->scene;
    }

    /**
     * 只生成一个验证码字符串，不写入缓存、不产生 token。
     *
     * @param int|null    $length 位数，null 使用全局配置
     * @param string|null $type   类型，null 使用全局配置
     */
    public function makeCode(?int $length = null, ?string $type = null): string
    {
        $codeConfig = (array) ($this->config['code'] ?? []);
        $length = $length ?? (int) ($codeConfig['length'] ?? 6);
        $type = $type ?? (string) ($codeConfig['type'] ?? Random::TYPE_NUMERIC);

        return Random::make($type, $length);
    }

    /**
     * 生成验证码并写入缓存，返回 token 与验证码明文。
     *
     * @param string      $account 手机号或邮箱
     * @param string|null $scene   业务场景，null 使用当前场景
     */
    public function generate(string $account, ?string $scene = null): Result
    {
        $account = trim($account);
        $scene = $this->resolveScene($scene);

        if ($account === '') {
            return Result::fail('接收账号不能为空', 400, ['scene' => $scene]);
        }

        $settings = $this->sceneSettings($scene);

        if ($this->limiter()->coolingDown($scene, $account, $settings['resend_interval'])) {
            return Result::fail(
                sprintf('生成过于频繁，请 %s 后再试', $this->humanDuration($settings['resend_interval'])),
                429,
                ['scene' => $scene]
            );
        }

        $limit = (array) ($this->config['limit'] ?? []);

        if (!$this->limiter()->allow(
            'create_account',
            $scene,
            $account,
            (int) ($limit['account_count'] ?? 0),
            (int) ($limit['account_window'] ?? 3600)
        )) {
            return Result::fail('该账号生成次数已达上限，请稍后再试', 429, ['scene' => $scene]);
        }

        if (!$this->limiter()->allow(
            'create_ip',
            $scene,
            $this->clientIp(),
            (int) ($limit['ip_count'] ?? 0),
            (int) ($limit['ip_window'] ?? 3600)
        )) {
            return Result::fail('当前 IP 生成次数已达上限，请稍后再试', 429, ['scene' => $scene]);
        }

        try {
            $code = Random::make($settings['type'], $settings['length']);
        } catch (\InvalidArgumentException $e) {
            return Result::fail($e->getMessage(), 400, ['scene' => $scene]);
        }

        try {
            $token = $this->codeStore()->store($scene, $account, $code, $settings['expire']);
        } catch (\Throwable $e) {
            return Result::fail('验证码生成失败，请稍后重试', 500, ['scene' => $scene]);
        }

        $this->limiter()->markCooldown($scene, $account, $settings['resend_interval']);

        return Result::success('验证码生成成功', [
            'token' => $token,
            'code' => $code,
            'scene' => $scene,
            'expire' => $settings['expire'],
            'expire_text' => $this->humanDuration($settings['expire']),
        ]);
    }

    /**
     * 校验验证码。
     *
     * @param string      $token   生成接口返回的 token
     * @param string      $code    用户输入的验证码
     * @param string      $account 手机号或邮箱，必须与生成时一致
     * @param string|null $scene   业务场景，必须与生成时一致
     */
    public function check(string $token, string $code, string $account, ?string $scene = null): Result
    {
        $scene = $this->resolveScene($scene);
        $token = trim($token);
        $code = trim($code);
        $account = trim($account);

        if ($token === '' || $code === '' || $account === '') {
            return Result::fail('token、验证码和接收账号不能为空', 400, ['scene' => $scene]);
        }

        $settings = $this->sceneSettings($scene);
        $success = $this->codeStore()->consume(
            $scene,
            $token,
            $code,
            $account,
            $settings['max_attempts']
        );

        if ($success) {
            return Result::success('验证码校验通过', ['scene' => $scene]);
        }

        return Result::fail('验证码错误或已失效', 400, ['scene' => $scene]);
    }

    /**
     * 校验验证码并返回布尔值。
     */
    public function verify(string $token, string $code, string $account, ?string $scene = null): bool
    {
        return $this->check($token, $code, $account, $scene)->isSuccess();
    }

    /**
     * 主动作废验证码。
     */
    public function forget(string $token, ?string $scene = null): bool
    {
        return $this->codeStore()->forget($this->resolveScene($scene), trim($token));
    }

    /**
     * 获取配置项。
     */
    public function getConfig(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->config;
        }

        return $this->config[$name] ?? $default;
    }

    /**
     * 解析当前场景最终生效的设置。
     *
     * @return array{expire: int, resend_interval: int, max_attempts: int, length: int, type: string}
     */
    protected function sceneSettings(string $scene): array
    {
        $sceneConfig = $this->sceneConfig($scene);
        $codeConfig = array_replace(
            (array) ($this->config['code'] ?? []),
            (array) ($sceneConfig['code'] ?? [])
        );

        return [
            'expire' => max(1, (int) ($sceneConfig['expire'] ?? $this->config['expire'] ?? 300)),
            'resend_interval' => max(0, (int) ($sceneConfig['resend_interval'] ?? $this->config['resend_interval'] ?? 0)),
            'max_attempts' => max(0, (int) ($sceneConfig['max_attempts'] ?? $this->config['max_attempts'] ?? 5)),
            'length' => max(1, (int) ($codeConfig['length'] ?? 6)),
            'type' => (string) ($codeConfig['type'] ?? Random::TYPE_NUMERIC),
        ];
    }

    /**
     * 获取场景配置，并与默认场景递归合并。
     */
    protected function sceneConfig(string $scene): array
    {
        $scenes = (array) ($this->config['scenes'] ?? []);
        $defaultScene = (string) ($this->config['default_scene'] ?? 'default');
        $base = (array) ($scenes[$defaultScene] ?? []);

        if ($scene === $defaultScene) {
            return $base;
        }

        return array_replace_recursive($base, (array) ($scenes[$scene] ?? []));
    }

    /**
     * 获取验证码核心。
     */
    protected function codeStore(): Code
    {
        if (!$this->codeStore instanceof Code) {
            $this->codeStore = new Code(
                $this->store,
                (string) ($this->config['secret'] ?? ''),
                (int) ($this->config['max_attempts'] ?? 5)
            );
        }

        return $this->codeStore;
    }

    /**
     * 获取频率限制器。
     */
    protected function limiter(): Limiter
    {
        if (!$this->limiter instanceof Limiter) {
            $this->limiter = new Limiter(
                $this->store,
                (int) ($this->config['resend_interval'] ?? 0)
            );
        }

        return $this->limiter;
    }

    /**
     * 解析场景名。
     */
    protected function resolveScene(?string $scene): string
    {
        if ($scene !== null && $scene !== '') {
            return $scene;
        }

        return $this->scene !== '' ? $this->scene : (string) ($this->config['default_scene'] ?? 'default');
    }

    /**
     * 获取客户端 IP。
     */
    protected function clientIp(): string
    {
        if ($this->ip !== null && $this->ip !== '') {
            return $this->ip;
        }

        try {
            if (function_exists('request')) {
                return (string) request()->ip();
            }

            if (function_exists('app')) {
                $request = app('request');

                if (is_object($request) && method_exists($request, 'ip')) {
                    return (string) $request->ip();
                }
            }
        } catch (\Throwable) {
            // CLI、队列等无请求上下文时使用 unknown
        }

        return 'unknown';
    }

    /**
     * 将秒数转换为中文时长。
     */
    protected function humanDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0秒';
        }

        $units = [
            86400 => '天',
            3600 => '小时',
            60 => '分钟',
        ];

        $parts = [];
        $remaining = $seconds;

        foreach ($units as $unitSeconds => $label) {
            if ($remaining >= $unitSeconds) {
                $value = intdiv($remaining, $unitSeconds);
                $parts[] = $value . $label;
                $remaining %= $unitSeconds;
            }
        }

        if ($remaining > 0 || $parts === []) {
            $parts[] = $remaining . '秒';
        }

        return implode('', $parts);
    }

    /**
     * 组件内置兜底配置。
     *
     * Service 会把 config/verifying.php 与项目配置合并后传入，
     * 这里保留一份最小默认值，便于脱离框架直接实例化时也能工作。
     */
    protected function defaultConfig(): array
    {
        return [
            'default_scene' => 'default',
            'cache' => [
                'store' => null,
                'prefix' => 'verifying',
            ],
            'secret' => '',
            'code' => [
                'length' => 6,
                'type' => Random::TYPE_NUMERIC,
            ],
            'expire' => 300,
            'resend_interval' => 60,
            'max_attempts' => 5,
            'limit' => [
                'account_count' => 10,
                'account_window' => 3600,
                'ip_count' => 30,
                'ip_window' => 3600,
            ],
            'scenes' => [
                'default' => [],
            ],
        ];
    }
}