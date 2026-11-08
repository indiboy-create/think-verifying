<?php
declare(strict_types=1);

namespace Youvision\ThinkVerifying\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkVerifying\Random;

class RandomTest extends TestCase
{
    public function testNumericCode(): void
    {
        $code = Random::numeric(6);

        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));
    }

    public function testNozeroCodeContainsNoZero(): void
    {
        $code = Random::nozero(8);

        $this->assertSame(8, strlen($code));
        $this->assertStringNotContainsString('0', $code);
        $this->assertTrue(ctype_digit($code));
    }

    public function testAlphaCode(): void
    {
        $code = Random::alpha(10);

        $this->assertSame(10, strlen($code));
        $this->assertTrue(ctype_alpha($code));
    }

    public function testAlnumCode(): void
    {
        $code = Random::alnum(12);

        $this->assertSame(12, strlen($code));
        $this->assertTrue(ctype_alnum($code));
    }

    public function testInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Random::numeric(0);
    }

    public function testInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Random::make('unknown', 6);
    }

    public function testToken(): void
    {
        $token = Random::token(16);

        $this->assertSame(32, strlen($token));
        $this->assertTrue(ctype_xdigit($token));
        $this->assertNotSame($token, Random::token(16));
    }
}