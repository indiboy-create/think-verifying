<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying;

/**
 * 统一返回结果。
 *
 * 结构保持与常见 ThinkPHP 接口一致：
 * [
 *     'code' => 200,
 *     'msg'  => '发送成功',
 *     'data' => ['token' => '...', 'expire' => 300],
 * ]
 */
class Result
{
    public function __construct(
        protected bool $success,
        protected int $code,
        protected string $message,
        protected array $data = []
    ) {
    }

    /**
     * 构造成功结果。
     */
    public static function success(string $message = '操作成功', array $data = []): self
    {
        return new self(true, 200, $message, $data);
    }

    /**
     * 构造失败结果。
     */
    public static function fail(string $message = '操作失败', int $code = 400, array $data = []): self
    {
        return new self(false, $code, $message, $data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取结果中的指定数据项。
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    /**
     * 获取发送成功后返回的校验 token。
     */
    public function token(): string
    {
        return (string) $this->get('token', '');
    }

    /**
     * 获取验证码有效期，单位秒。
     */
    public function expire(): int
    {
        return (int) $this->get('expire', 0);
    }

    /**
     * 转换为数组。
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'msg' => $this->message,
            'data' => $this->data,
        ];
    }

    /**
     * 转换为 JSON。
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}