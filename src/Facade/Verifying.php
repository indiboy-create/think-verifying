<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Facade;

use think\Facade;
use Youvision\ThinkVerifying\Verifying as VerifyingManager;

/**
 * 验证码门面。
 *
 * @method static VerifyingManager scene(string $scene)
 * @method static string sceneName()
 * @method static string makeCode(?int $length = null, ?string $type = null)
 * @method static \Youvision\ThinkVerifying\Result generate(string $account, ?string $scene = null)
 * @method static \Youvision\ThinkVerifying\Result check(string $token, string $code, string $account, ?string $scene = null)
 * @method static bool verify(string $token, string $code, string $account, ?string $scene = null)
 * @method static bool forget(string $token, ?string $scene = null)
 * @method static mixed getConfig(?string $name = null, mixed $default = null)
 *
 * @see VerifyingManager
 */
class Verifying extends Facade
{
    protected static function getFacadeClass(): string
    {
        return VerifyingManager::class;
    }
}