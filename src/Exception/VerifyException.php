<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Exception;

/**
 * 验证码异常。
 *
 * 缓存写入失败等内部错误由核心类抛出，由 Verifying 捕获并转换为 Result。
 */
class VerifyException extends \RuntimeException
{
}