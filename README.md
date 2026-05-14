# erikwang2013/jwt-webman

一款兼容 webman、Laravel、ThinkPHP、Hyperf 的 JWT 认证插件。适用于分布式部署，安装简单快捷。

作者：[艾瑞可erik](https://erik.xyz)

## 功能特性

- JWT 令牌生成（支持 HS256 / HS384 / HS512 / RS256 算法）
- 令牌验证（支持时间容差 leeway）
- 刷新令牌（Refresh Token）
- 令牌黑名单（支持 redis、database、memcached、file 四种存储驱动）
- 存储操作失败自动重试
- 多存储后端优雅降级
- 四框架深度集成：中间件、门面模式、安装命令

## 安装

```sh
composer require erikwang2013/jwt-webman
```

## 各框架使用说明

### Webman

`composer require` 后通过 webman 插件系统自动注册，无需手动配置。

**配置文件：** `config/plugin/erikwang2013/jwt/jwt.php`

**基本用法：**

```php
use ErikJwt\JWTFactory;

$jwt = JWTFactory::createFromConfig(
    config('plugin.erikwang2013.jwt.jwt'),
    null,
    [
        'redis' => fn() => \support\Redis::connection(),
        'pdo'   => \support\Db::connection()->getPdo(),
    ]
);

// 生成令牌
$token = $jwt->encode(['user_id' => 1]);

// 验证令牌
$payload = $jwt->decode($token);

// 拉黑令牌
$jwt->blacklist($token);
```

**中间件：** 在 `config/middleware.php` 中注册：

```php
return [
    '' => [
        \ErikJwt\Webman\Middleware::class,
    ],
];
```

在控制器中获取解析后的 payload：

```php
$payload = $request->jwt_payload;
$userId  = $payload['user_id'];
```

---

### Laravel

`composer require` 后通过 `extra.laravel` 自动发现 ServiceProvider。如果关闭了自动发现，手动在 `config/app.php` 中注册：

```php
'providers' => [
    ErikJwt\Laravel\JWTServiceProvider::class,
],
```

**安装命令：**

```sh
php artisan jwt:install
```

执行后会自动发布配置文件并生成 `JWT_SECRET_KEY` 写入 `.env`。

**配置文件：** `config/jwt.php`

**门面方式：**

```php
use ErikJwt\Laravel\Facade as JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
JWT::blacklist($token);
```

**辅助函数：**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**依赖注入：**

```php
use ErikJwt\JWT;

public function __construct(JWT $jwt) {
    $this->jwt = $jwt;
}
```

**中间件：**

```php
// 路由中使用
Route::middleware('jwt')->group(function () {
    Route::get('/api/user', [UserController::class, 'index']);
});

// 控制器中获取 payload
public function index(Request $request) {
    $payload = $request->attributes->get('jwt_payload');
    $userId  = $payload['user_id'];
}
```

**手动发布配置：**

```sh
php artisan vendor:publish --tag=jwt-config
```

---

### ThinkPHP

`composer require` 后在 `app/service.php` 中注册服务：

```php
return [
    \ErikJwt\ThinkPHP\JWTService::class,
];
```

**安装命令：**

```sh
php think jwt:install
```

**配置文件：** `config/jwt.php`

**门面方式：**

```php
use ErikJwt\ThinkPHP\JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
```

**辅助函数：**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**中间件：**

```php
// 路由中使用
Route::group(function () {
    Route::get('/api/user', 'UserController@index');
})->middleware('jwt');

// 控制器中获取 payload
public function index(Request $request) {
    $payload = $request->jwt_payload;
    $userId  = $payload['user_id'];
}
```

---

### Hyperf

`composer require` 后在 `config/autoload/dependencies.php` 中注册 ConfigProvider：

```php
return [
    \ErikJwt\Hyperf\ConfigProvider::class,
];
```

**安装命令：**

```sh
php bin/hyperf.php jwt:install
```

**配置文件：** `config/autoload/jwt.php`

**依赖注入：**

```php
use ErikJwt\JWT;
use Hyperf\Di\Annotation\Inject;

class UserController {
    #[Inject]
    protected JWT $jwt;

    public function index() {
        $token   = $this->jwt->encode(['user_id' => 1]);
        $payload = $this->jwt->decode($token);
    }
}
```

**中间件：** ConfigProvider 已自动注册，在 `config/autoload/middlewares.php` 中配置即可。

**AOP 注解方式（可选）：**

```php
use ErikJwt\Hyperf\JWT as JWTAuth;

class UserController {
    #[JWTAuth]
    public function index() {
        // 方法执行前自动校验 JWT
    }
}
```

---

## 配置文件参考

```php
return [
    // 签名密钥，至少16字符
    'secret_key'     => env('JWT_SECRET_KEY', ''),
    // 签名算法：HS256 / HS384 / HS512 / RS256
    'algorithm'      => env('JWT_ALGORITHM', 'HS256'),
    // 签发者标识
    'issuer'         => env('JWT_ISSUER', ''),
    // 受众标识
    'audience'       => env('JWT_AUDIENCE', ''),
    // 时间容差（秒），用于处理服务器时钟偏差
    'leeway'         => (int) env('JWT_LEEWAY', 0),
    // 默认令牌过期时间（秒）
    'default_expire' => (int) env('JWT_DEFAULT_EXPIRE', 3600),
    // 刷新令牌过期时间（秒）
    'refresh_expire' => (int) env('JWT_REFRESH_EXPIRE', 7200),
    // 黑名单存储配置
    'storage' => [
        // 存储类型：file / redis / database / memcached
        'type'     => env('JWT_STORAGE_TYPE', 'file'),
        // 缓存键前缀
        'prefix'   => env('JWT_STORAGE_PREFIX', 'jwt_blacklist:'),
        // Redis 数据库编号
        'database' => (int) env('JWT_STORAGE_DATABASE', 0),
    ],
    // 高级配置
    'advanced' => [
        // 操作失败重试次数
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        // 重试延迟（毫秒）
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        // 是否自动清理过期条目
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        // 自动清理间隔（秒）
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    // 中间件配置
    'middleware' => [
        // 排除的路由路径（正则），这些路径不校验 JWT
        'except' => [],
    ],
];
```

## 存储驱动对比

| 驱动 | 适用场景 |
|------|----------|
| `file` | 单机部署、低并发 |
| `redis` | 分布式部署、高性能 |
| `database` | 需要持久化、跨数据中心 |
| `memcached` | 高吞吐量、自动过期 |

## 开源协议

MIT
