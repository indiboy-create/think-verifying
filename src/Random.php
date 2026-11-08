<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying;

/**
 * 安全随机串生成器。
 *
 * 与参考库使用 str_shuffle / mt_rand 不同，这里全部使用 PHP 的
 * random_int() 与 random_bytes()，避免验证码被可预测的伪随机数算法推算。
 */
class Random
{
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_NOZERO = 'nozero';
    public const TYPE_ALPHA = 'alpha';
    public const TYPE_ALNUM = 'alnum';

    /**
     * 生成纯数字验证码。
     */
    public static function numeric(int $length = 6): string
    {
        return self::make(self::TYPE_NUMERIC, $length);
    }

    /**
     * 生成不含 0 的数字验证码，避免 0 与字母 O 混淆。
     */
    public static function nozero(int $length = 6): string
    {
        return self::make(self::TYPE_NOZERO, $length);
    }

    /**
     * 生成纯字母验证码。
     */
    public static function alpha(int $length = 6): string
    {
        return self::make(self::TYPE_ALPHA, $length);
    }

    /**
     * 生成数字 + 字母验证码。
     */
    public static function alnum(int $length = 6): string
    {
        return self::make(self::TYPE_ALNUM, $length);
    }

    /**
     * 按类型生成指定长度的验证码。
     *
     * @param string $type   numeric / nozero / alpha / alnum
     * @param int    $length 验证码位数
     *
     * @throws \InvalidArgumentException 类型或长度非法时抛出
     */
    public static function make(string $type = self::TYPE_NUMERIC, int $length = 6): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('验证码长度必须大于 0');
        }

        $pool = match ($type) {
            self::TYPE_NUMERIC => '0123456789',
            self::TYPE_NOZERO => '123456789',
            self::TYPE_ALPHA => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            self::TYPE_ALNUM => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            default => throw new \InvalidArgumentException(sprintf('不支持的验证码类型：%s', $type)),
        };

        $max = strlen($pool) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $pool[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * 生成十六进制随机 token。
     *
     * @param int $bytes 随机字节数，最终字符串长度为 bytes * 2
     */
    public static function token(int $bytes = 16): string
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('token 字节数必须大于 0');
        }

        return bin2hex(random_bytes($bytes));
    }
}