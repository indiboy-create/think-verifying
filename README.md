## ThinkPHP-PanGu - 盘古ThinkPHP开源索引!
面向 ThinkPHP 的开源工程索引：组件、框架、服务与发布工具，按能力组织，持续整理。
仓库地址：**<https://github.com/indiboy-create/ThinkPHP-PanGu/>**
在线站点：**<https://indiboy-create.github.io/ThinkPHP-PanGu/>**

# youvision/think-verifying

ThinkPHP 8 验证码生成与校验组件，只专注验证码核心逻辑，不包含短信、邮件等发送实现。业务层拿到验证码后，可自行通过 `youvision/think-easysms`、`youvision/think-mailer` 或其它通道发送。

## 功能特性

- 生成数字、无零数字、纯字母、数字字母混合验证码
- 生成成功返回一次性 token，校验时同时提交 token、验证码和接收账号
- 场景隔离：注册、登录、找回密码等场景的验证码互不通用
- 场景可覆盖有效期、验证码位数、类型、冷却时间与错误次数
- 一次性消费：校验通过后验证码立即失效，无法重放
- 错误次数限制：连续输错达到上限后验证码立即作废
- 生成冷却与频率限制：支持账号维度、IP 维度、时间窗口三个维度
- 安全存储：缓存中不保存明文 token 与明文验证码，只保存 HMAC-SHA256 摘要
- 支持门面、依赖注入与 `verifying()` 助手三种调用方式
- 仅依赖 ThinkPHP 8，不绑定任何短信或邮件服务商

## 环境要求

- PHP >= 8.0
- ThinkPHP >= 8.0

## 安装

```bash
composer require youvision/think-verifying
```

ThinkPHP 8 项目通常会在 `composer.json` 中配置以下脚本，安装完成后自动发现服务并发布配置：

```json
"scripts": {
    "post-autoload-dump": [
        "@php think service:discover",
        "@php think vendor:publish"
    ]
}
```

若没有自动执行，可手动运行：

```bash
php think service:discover
php think vendor:publish
```

执行后项目 `config/` 目录会生成 `config/verifying.php`。

## 配置说明

| 配置项 | 说明 |
| --- | --- |
| `default_scene` | 默认业务场景，未显式指定场景时使用，默认 `default` |
| `cache.store` | 缓存驱动名，对应 `config/cache.php` 中 `stores` 下的键；`null` 表示默认驱动 |
| `cache.prefix` | 所有缓存键的公共前缀，默认 `verifying` |
| `secret` | 验证码 HMAC 签名密钥，默认读取 `VERIFYING_SECRET`，未配置时回退到 `APP_KEY` |
| `code.length` | 验证码位数，默认 6 |
| `code.type` | 验证码类型：`numeric` / `nozero` / `alpha` / `alnum`，默认 `numeric` |
| `expire` | 验证码有效期，单位秒，默认 300 |
| `resend_interval` | 同场景同账号生成成功后的冷却时间，单位秒；0 表示关闭 |
| `max_attempts` | 单个 token 允许的最大错误次数；0 表示不限制，默认 5 |
| `limit.account_count` | 同场景同账号在时间窗口内允许生成的最大次数；0 表示不限制 |
| `limit.account_window` | 账号生成计数的时间窗口，单位秒 |
| `limit.ip_count` | 同场景同 IP 在时间窗口内允许生成的最大次数；0 表示不限制 |
| `limit.ip_window` | IP 生成计数的时间窗口，单位秒 |
| `scenes` | 业务场景配置，可覆盖 `expire`、`resend_interval`、`max_attempts`、`code.length`、`code.type` |

场景配置示例：

```php
'scenes' => [
    'default' => [],

    'register' => [
        'expire'          => 600,
        'resend_interval' => 120,
        'max_attempts'    => 3,
        'code'            => [
            'length' => 6,
            'type'   => 'numeric',
        ],
    ],

    'login' => [
        'expire' => 300,
        'code'   => [
            'length' => 4,
            'type'   => 'nozero',
        ],
    ],
],
```

未在 `scenes` 中出现的场景直接使用全局配置；场景中未配置的项自动继承全局配置。

> 多机部署必须使用 Redis / Memcached 等共享缓存，否则验证码无法跨服务器校验。

## 快速开始

### 生成验证码

```php
use Youvision\ThinkVerifying\Facade\Verifying;

$result = Verifying::scene('register')->generate('13800138000');

if (!$result->isSuccess()) {
    return json($result->toArray());
}

$code  = (string) $result->get('code');
$token = $result->token();

// 业务层自行通过短信、邮件等通道发送 $code
// 注意：$code 只能发送给用户，不能直接返回给前端

// 前端只需要保存 token
return json([
    'token'  => $token,
    'expire' => $result->expire(),
]);
```

### 校验验证码

```php
$result = Verifying::scene('register')->check($token, $inputCode, '13800138000');

if (!$result->isSuccess()) {
    return json($result->toArray());
}

// 校验通过，继续注册业务
```

也可以直接获取布尔值：

```php
$ok = Verifying::scene('register')->verify($token, $inputCode, '13800138000');
```

### 使用依赖注入

```php
use Youvision\ThinkVerifying\Verifying;

class Register
{
    public function __construct(protected Verifying $verifying)
    {
    }

    public function generate(string $mobile)
    {
        $result = $this->verifying->scene('register')->generate($mobile);

        return $result->toArray();
    }
}
```

### 使用助手函数

```php
$result = verifying('register')->generate('13800138000');

$ok = verifying('register')->verify($token, $code, '13800138000');
```

### 不切换场景的写法

```php
Verifying::generate('13800138000', 'register');
Verifying::check($token, $code, '13800138000', 'register');
```

## 配合短信 / 邮件发送组件

组件本身不发送验证码，以下为可选集成示例。

短信（`youvision/think-easysms`）：

```php
use Youvision\ThinkEasySms\Sms;

$result = Verifying::scene('register')->generate($mobile);
$code = (string) $result->get('code');

Sms::send($mobile, [
    'content'  => '您的验证码是：' . $code,
    'template' => 'SMS_123456789',
    'data'     => ['code' => $code],
]);
```

邮件（`youvision/think-mailer`）：

```php
use youvision\mailer\Mail;

$result = Verifying::scene('register')->generate($email);
$code = (string) $result->get('code');

Mail::to($email)
    ->subject('注册验证码')
    ->html('您的验证码是：<b>' . $code . '</b>，5 分钟内有效。')
    ->send();
```

## 返回结果

生成与校验统一返回 `Youvision\ThinkVerifying\Result`：

```php
[
    'code' => 200,
    'msg'  => '验证码生成成功',
    'data' => [
        'token'       => '9f1c...',
        'code'        => '123456',
        'scene'       => 'register',
        'expire'      => 600,
        'expire_text' => '10分钟',
    ],
]
```

| 方法 | 说明 |
| --- | --- |
| `isSuccess()` | 是否成功 |
| `getCode()` | 结果码：200 成功、400 参数或验证码错误、429 频率限制、500 生成失败 |
| `getMessage()` | 提示消息 |
| `getData()` | 全部数据 |
| `get($name, $default)` | 获取指定数据项 |
| `token()` | 获取校验 token |
| `expire()` | 获取有效期秒数 |
| `toArray()` | 转换为数组 |
| `toJson()` | 转换为 JSON |

## 安全设计

- 生成结果包含验证码明文，因为组件不负责发送；业务层必须通过短信、邮件等可信通道发送给用户，禁止把验证码直接返回给前端
- 缓存键只包含 token 的 SHA-256 摘要，缓存值只保存验证码的 HMAC-SHA256 摘要
- 校验时必须同时匹配 scene、account、code，三者任一不一致都无法通过
- 校验成功立即删除缓存项，验证码只能使用一次
- 连续输错达到 `max_attempts` 后立即删除缓存项，防止暴力枚举
- 生成成功后进入 `resend_interval` 冷却期；账号与 IP 维度分别有独立的时间窗口限制
- 多机部署必须使用 Redis / Memcached 等共享缓存


## 测试

```bash
composer install
vendor/bin/phpunit
```

## License

MIT