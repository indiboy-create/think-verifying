<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying;

use Youvision\ThinkVerifying\Cache\Store;
use Youvision\ThinkVerifying\Exception\VerifyException;

/**
 * 验证码缓存与校验核心。
 *
 * 安全设计：
 *  - 缓存键只包含 token 的 SHA-256 摘要，不保存明文 token；
 *  - 缓存值只保存验证码的 HMAC-SHA256 摘要，不保存明文验证码；
 *  - 校验通过后立即删除缓存项，验证码只能使用一次；
 *  - 连续输错达到上限后立即删除缓存项，防止暴力枚举；
 *  - 所有摘要比较使用 hash_equals()，避免时序攻击。
 */
class Code
{
    /**
     * 未配置 secret 时使用的兜底密钥。
     */
    protected const FALLBACK_SECRET = 'youvision-think-verifying';

    public function __construct(
        protected Store $store,
        protected string $secret = '',
        protected int $maxAttempts = 5
    ) {
    }

    /**
     * 保存验证码并返回一次性 token。
     *
     * @param string $scene   业务场景
     * @param string $account 手机号或邮箱
     * @param string $code    明文验证码
     * @param int    $expire  有效期，单位秒
     *
     * @throws VerifyException 缓存写入失败时抛出
     */
    public function store(string $scene, string $account, string $code, int $expire): string
    {
        $token = Random::token(16);
        $key = $this->key($scene, $token);
        $now = time();

        $entry = [
            'scene' => $scene,
            'account' => $this->hashValue($account),
            'code' => $this->hashCode($scene, $account, $code),
            'attempts' => 0,
            'expires_at' => $now + $expire,
        ];

        if (!$this->store->set($key, $entry, $expire)) {
            throw new VerifyException('验证码写入缓存失败');
        }

        return $token;
    }

    /**
     * 校验并消费验证码。
     *
     * 校验成功后缓存项立即删除；校验失败会累加错误次数，
     * 达到 maxAttempts 后缓存项作废。
     *
     * @param string   $scene       业务场景，必须与生成时一致
     * @param string   $token       生成接口返回的 token
     * @param string   $code        用户输入的验证码
     * @param string   $account     手机号或邮箱，必须与生成时一致
     * @param int|null $maxAttempts 当前场景允许的最大错误次数，null 使用构造时的值
     */
    public function consume(string $scene, string $token, string $code, string $account, ?int $maxAttempts = null): bool
    {
        $maxAttempts = $maxAttempts ?? $this->maxAttempts;
        $key = $this->key($scene, $token);
        $entry = $this->store->get($key);

        if (!is_array($entry)) {
            return false;
        }

        if (($entry['expires_at'] ?? 0) <= time()) {
            $this->store->delete($key);

            return false;
        }

        if (!isset($entry['scene'], $entry['account'], $entry['code'])) {
            $this->store->delete($key);

            return false;
        }

        if (!hash_equals((string) $entry['scene'], $scene)) {
            return false;
        }

        if (!hash_equals((string) $entry['account'], $this->hashValue($account))) {
            return false;
        }

        $attempts = (int) ($entry['attempts'] ?? 0);

        if ($maxAttempts > 0 && $attempts >= $maxAttempts) {
            $this->store->delete($key);

            return false;
        }

        if (hash_equals((string) $entry['code'], $this->hashCode($scene, $account, $code))) {
            $this->store->delete($key);

            return true;
        }

        $entry['attempts'] = $attempts + 1;

        if ($maxAttempts > 0 && $entry['attempts'] >= $maxAttempts) {
            $this->store->delete($key);
        } else {
            $ttl = max(1, (int) $entry['expires_at'] - time());
            $this->store->set($key, $entry, $ttl);
        }

        return false;
    }

    /**
     * 主动作废某个 token 对应的验证码。
     */
    public function forget(string $scene, string $token): bool
    {
        return $this->store->delete($this->key($scene, $token));
    }

    /**
     * 生成缓存键。
     */
    protected function key(string $scene, string $token): string
    {
        return 'code:' . $this->slug($scene) . ':' . hash('sha256', $token);
    }

    /**
     * 计算验证码摘要。
     */
    protected function hashCode(string $scene, string $account, string $code): string
    {
        return hash_hmac('sha256', 'code|' . $scene . '|' . $account . '|' . $code, $this->secretKey());
    }

    /**
     * 计算账号摘要，避免手机号、邮箱明文出现在缓存中。
     */
    protected function hashValue(string $value): string
    {
        return hash_hmac('sha256', 'value|' . $value, $this->secretKey());
    }

    /**
     * 获取实际使用的签名密钥。
     */
    protected function secretKey(): string
    {
        return $this->secret !== '' ? $this->secret : self::FALLBACK_SECRET;
    }

    /**
     * 场景名转缓存键安全字符。
     */
    protected function slug(string $scene): string
    {
        $scene = (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $scene);

        return $scene !== '' ? $scene : 'default';
    }
}