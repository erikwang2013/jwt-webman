# erikwang2013/jwt-webman

A JWT authentication plugin compatible with webman, Laravel, ThinkPHP, and Hyperf. Suitable for distributed deployment, with simple and fast installation.

Author: [艾瑞可erik](https://erik.xyz)

## Features

- JWT token generation (HS256/HS384/HS512/RS256)
- Token validation with time tolerance
- Refresh token support
- Token blacklist (redis, database, memcached, file)
- Automatic retry on storage failure
- Graceful degradation with fallback storage
- Multi-framework deep integration: Middleware, Facade, InstallCommand

## Installation

```sh
composer require erikwang2013/jwt-webman
```

## Framework Integration

### Webman

After `composer require`, the plugin auto-registers via webman's plugin system.

**Config:** `config/plugin/erikwang2013/jwt/jwt.php`

**Usage:**

```php
use ErikJwt\JWTFactory;

$jwt = JWTFactory::createFromConfig(
    config('plugin.erikwang2013.jwt.jwt'),
    null,
    [
        'redis' => fn() => \support\Redis::class,
        'pdo'   => \support\Db::connection()->getPdo(),
    ]
);

$token   = $jwt->encode(['user_id' => 1]);
$payload = $jwt->decode($token);
$jwt->blacklist($token);
```

**Middleware:** Register in `config/middleware.php`:

```php
return [
    '' => [
        \ErikJwt\Webman\Middleware::class,
    ],
];
```

---

### Laravel

After `composer require`, Laravel auto-discovers the ServiceProvider via `extra.laravel` in composer.json. If auto-discovery is disabled, add to `config/app.php`:

```php
'providers' => [
    ErikJwt\Laravel\JWTServiceProvider::class,
],
```

**Install:**

```sh
php artisan jwt:install
```

**Config:** `config/jwt.php`

**Usage — Facade:**

```php
use ErikJwt\Laravel\Facade as JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
JWT::blacklist($token);
```

**Usage — Helper:**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**Usage — Dependency Injection:**

```php
use ErikJwt\JWT;

public function __construct(JWT $jwt) {
    $this->jwt = $jwt;
}
```

**Middleware:**

```php
Route::middleware('jwt')->group(function () {
    Route::get('/api/user', [UserController::class, 'index']);
});

// In controller
public function index(Request $request) {
    $payload = $request->attributes->get('jwt_payload');
}
```

**Config publishing:**

```sh
php artisan vendor:publish --tag=jwt-config
```

---

### ThinkPHP

Register the service in `app/service.php` after `composer require`:

```php
return [
    \ErikJwt\ThinkPHP\JWTService::class,
];
```

**Install:**

```sh
php think jwt:install
```

**Config:** `config/jwt.php`

**Usage — Facade:**

```php
use ErikJwt\ThinkPHP\JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
```

**Usage — Helper:**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**Middleware:**

```php
Route::group(function () {
    Route::get('/api/user', 'UserController@index');
})->middleware('jwt');

// In controller
public function index(Request $request) {
    $payload = $request->jwt_payload;
}
```

---

### Hyperf

After `composer require`, register ConfigProvider in `config/autoload/dependencies.php`:

```php
return [
    \ErikJwt\Hyperf\ConfigProvider::class,
];
```

**Install:**

```sh
php bin/hyperf.php jwt:install
```

**Config:** `config/autoload/jwt.php`

**Usage — Dependency Injection:**

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

**Middleware:** already registered by ConfigProvider in `config/autoload/middlewares.php`.

**AOP Annotation:**

```php
use ErikJwt\Hyperf\JWT as JWTAuth;

class UserController {
    #[JWTAuth]
    public function index() {
        // Auto validates JWT before execution
    }
}
```

---

## Config Reference

```php
return [
    'secret_key'     => env('JWT_SECRET_KEY', ''),
    'algorithm'      => env('JWT_ALGORITHM', 'HS256'),
    'issuer'         => env('JWT_ISSUER', ''),
    'audience'       => env('JWT_AUDIENCE', ''),
    'leeway'         => (int) env('JWT_LEEWAY', 0),
    'default_expire' => (int) env('JWT_DEFAULT_EXPIRE', 3600),
    'refresh_expire' => (int) env('JWT_REFRESH_EXPIRE', 7200),
    'storage' => [
        'type'     => env('JWT_STORAGE_TYPE', 'file'),
        'prefix'   => env('JWT_STORAGE_PREFIX', 'jwt_blacklist:'),
        'database' => (int) env('JWT_STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => [],
    ],
];
```

| Storage Type | Best For |
|-------------|----------|
| `file` | Single-server, low traffic |
| `redis` | Distributed, high performance |
| `database` | Persistent, cross-datacenter |
| `memcached` | High throughput, auto-expiry |

## License

MIT
