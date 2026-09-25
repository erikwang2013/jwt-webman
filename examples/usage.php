<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\Jwt\Config;
use Erikwang2013\Jwt\JWTFactory;
use Erikwang2013\Jwt\JWTException;

// Framework-agnostic example
$config = [
    'secret_key'     => 'test-secret-key-at-least-16-chars',
    'algorithm'      => 'HS256',
    'issuer'         => 'test-app',
    'audience'       => 'test-users',
    'leeway'         => 60,
    'default_expire' => 3600,
    'refresh_expire' => 7200,
    'storage'        => ['type' => 'file'],
    'advanced'       => [
        'retry_attempts' => 1,
        'auto_cleanup'   => false,
    ],
];

try {
    $jwt = JWTFactory::createFromConfig($config);

    $token = $jwt->encode(['user_id' => 123, 'username' => 'testuser']);
    echo "Token generated: " . substr($token, 0, 50) . "...\n";

    $refreshToken = $jwt->encode(['user_id' => 123, 'token_type' => 'refresh'], 86400);
    echo "Refresh token generated\n";

    $payload = $jwt->decode($token);
    echo "Token validated for user: " . $payload['username'] . "\n";

    echo "Token valid: " . ($jwt->validate($token) ? 'yes' : 'no') . "\n";

    $jwt->blacklist($token);
    echo "Token blacklisted\n";

    if ($jwt->isBlacklisted($token)) {
        echo "Token correctly identified as blacklisted\n";
    }

    // 原生 PHP（无框架）：从配置文件创建，用 Guard 守卫入口脚本
    $configFile = __DIR__ . '/jwt.config.php';
    file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

    $guard = \Erikwang2013\Jwt\Native\Guard::fromFile($configFile);
    $requestToken = $guard->getJWT()->encode(['user_id' => 123]);

    // 模拟带 Authorization: Bearer 的请求
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $requestToken;
    echo "Guard payload for user: " . $guard->authenticate()['user_id'] . "\n";
    echo "Guard check: " . ($guard->check() ? 'valid' : 'invalid') . "\n";
    unset($_SERVER['HTTP_AUTHORIZATION']);

    // 未携带令牌时 check() 返回 false，authenticate() 抛 JWTException
    echo "Guard without token: " . ($guard->check() ? 'valid' : 'invalid') . "\n";

    unlink($configFile);

} catch (JWTException $e) {
    switch ($e->getCode()) {
        case JWTException::STORAGE_ERROR:
            echo "Storage error: " . $e->getMessage() . "\n";
            break;
        case JWTException::NETWORK_ERROR:
            echo "Network error: " . $e->getMessage() . "\n";
            break;
        case JWTException::CONFIG_ERROR:
            echo "Configuration error: " . $e->getMessage() . "\n";
            break;
        default:
            echo "JWT error: " . $e->getMessage() . "\n";
            break;
    }
} catch (Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
