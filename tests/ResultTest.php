<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkVerifying\Result;

class ResultTest extends TestCase
{
    public function testSuccess(): void
    {
        $result = Result::success('发送成功', ['token' => 'abc', 'expire' => 300]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(200, $result->getCode());
        $this->assertSame('发送成功', $result->getMessage());
        $this->assertSame('abc', $result->token());
        $this->assertSame(300, $result->expire());
        $this->assertSame('abc', $result->get('token'));
        $this->assertSame('default', $result->get('missing', 'default'));
        $this->assertSame([
            'code' => 200,
            'msg' => '发送成功',
            'data' => ['token' => 'abc', 'expire' => 300],
        ], $result->toArray());
    }

    public function testFail(): void
    {
        $result = Result::fail('验证码错误', 400, ['scene' => 'login']);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(400, $result->getCode());
        $this->assertSame('验证码错误', $result->getMessage());
        $this->assertSame('login', $result->get('scene'));
        $this->assertStringContainsString('验证码错误', (string) $result);
    }
}