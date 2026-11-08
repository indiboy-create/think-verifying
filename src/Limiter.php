<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying;

use Youvision\ThinkVerifying\Cache\Store;

/**
 * 生成冷却与频率限制。
 *
 * 与验证码本身分开存储，避免验证码被消费后频率计数一并丢失。
 * 场景可覆盖全局 cooldown，因此冷却方法的 cooldown 参数允许按场景传入。
 */
class Limiter
{
    /**
     * @param Store $store    缓存实现
     * @param int   $cooldown 默认同账号生成冷却时间，单位秒；0 表示关闭
     */
    public function __construct(
        protected Store $store,
        protected int $cooldown = 0
    ) {
    }

    /**
     * 判断是否仍处于生成冷却期。
     *
     * @param int|null $cooldown 当前场景的冷却秒数，null 使用构造时的值
     */
    public function coolingDown(string $scene, string $account, ?int $cooldown = null): bool
    {
        $cooldown = $cooldown ?? $this->cooldown;

        if ($cooldown <= 0) {
            return false;
        }

        return $this->store->has($this->key('cooldown', $scene, $account));
    }

    /**
     * 标记生成冷却。
     *
     * @param int|null $cooldown 当前场景的冷却秒数，null 使用构造时的值
     */
    public function markCooldown(string $scene, string $account, ?int $cooldown = null): void
    {
        $cooldown = $cooldown ?? $this->cooldown;

        if ($cooldown <= 0) {
            return;
        }

        $this->store->set($this->key('cooldown', $scene, $account), 1, $cooldown);
    }

    /**
     * 在固定时间窗口内消耗一次配额。
     *
     * @param string $scope    限制维度，如 create_account / create_ip
     * @param string $scene    业务场景
     * @param string $identity 手机号、邮箱或 IP
     * @param int    $limit    时间窗口内允许的最大次数；0 表示不限制
     * @param int    $window   时间窗口，单位秒
     *
     * @return bool true 表示本次请求在限额内
     */
    public function allow(string $scope, string $scene, string $identity, int $limit, int $window): bool
    {
        if ($limit <= 0) {
            return true;
        }

        $count = $this->store->increment(
            $this->key('limit', $scene, $identity, $scope),
            1,
            max(1, $window)
        );

        return $count <= $limit;
    }

    /**
     * 生成频率限制缓存键。
     */
    protected function key(string $type, string $scene, string $identity, string $scope = ''): string
    {
        $key = $type . ':' . $this->slug($scene) . ':' . hash('sha256', $identity);

        return $scope !== '' ? $key . ':' . $this->slug($scope) : $key;
    }

    /**
     * 场景名转缓存键安全字符。
     */
    protected function slug(string $value): string
    {
        $value = (string) preg_replace('/[^A-Za-z0-9_.-]/', '_', $value);

        return $value !== '' ? $value : 'default';
    }
}