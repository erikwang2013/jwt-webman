## 简介 ##
aaron-dev/xhprof-webman是一款适配webman的jwt插件。
主要是适用分布式部署，用于适配webman，安装简单快捷。

## 作者博客 ##
[艾瑞可erik](https://erik.xyz)

## 安装 ##



Use [Composer](https://github.com/composer/composer):
```sh
composer erikwang2013/jwt-webman
```


## 使用示例 ##


```

use ErikJwt\Config;
use ErikJwt\JWTFactory;
use ErikJwt\JWTException;



try {
    // 创建JWT实例
    $jwt = JWTFactory::createFromConfig();
    
    // 生成令牌
    $token = $jwt->encode(['user_id' => 123, 'username' => 'testuser']);
    echo "Token generated: " . substr($token, 0, 50) . "...\n";
    
    // 验证令牌
    $payload = $jwt->decode($token);
    echo "Token validated for user: " . $payload['username'] . "\n";
    
    // 将令牌加入黑名单
    $jwt->blacklist($token);
    echo "Token blacklisted\n";
    
    // 尝试再次验证黑名单中的令牌
    try {
        $jwt->decode($token);
        echo "ERROR: Token should be blacklisted!\n";
    } catch (JWTException $e) {
        echo "Correctly blocked blacklisted token: " . $e->getMessage() . "\n";
    }
    
} catch (JWTException $e) {
    // 处理不同类型的异常
    switch ($e->getCode()) {
        case JWTException::STORAGE_ERROR:
            echo "Storage error: " . $e->getMessage() . "\n";
            // 可以回退到文件存储
            $fallbackConfig = new Config([
                'secret_key' => 'your-secret-key',
                'storage' => ['type' => 'file']
            ]);
            $jwt = JWTFactory::createFromConfig($fallbackConfig);
            echo "Fallback to file storage\n";
            break;
            
        case JWTException::NETWORK_ERROR:
            echo "Network error: " . $e->getMessage() . "\n";
            // 记录日志，通知管理员等
            break;
            
        case JWTException::CONFIG_ERROR:
            echo "Configuration error: " . $e->getMessage() . "\n";
            // 检查配置文件
            break;
            
        default:
            echo "JWT error: " . $e->getMessage() . "\n";
            break;
    }
} catch (Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}

// 优雅降级示例
function createJWTWithFallback(array $configs): \ErikJwt\JWT
{
    $lastException = null;
    
    foreach ($configs as $config) {
        try {
            return JWTFactory::createFromConfig(new \ErikJwt\Config($config));
        } catch (JWTException $e) {
            $lastException = $e;
            // 继续尝试下一个配置
            continue;
        }
    }
    
    // 所有配置都失败，抛出最后一个异常
    throw $lastException;
}

// 使用多个存储后端配置
$configs = [
    [
        'secret_key' => 'your-secret-key',
        'storage' => [
            'type' => 'redis',
            'config' => ['host' => '127.0.0.1', 'port' => 6379]
        ]
    ],
    [
        'secret_key' => 'your-secret-key', 
        'storage' => [
            'type' => 'database',
            'config' => ['dsn' => 'mysql:host=127.0.0.1;dbname=test']
        ]
    ],
    [
        'secret_key' => 'your-secret-key',
        'storage' => ['type' => 'file']
    ]
];

try {
    $jwt = createJWTWithFallback($configs);
    echo "JWT instance created successfully with fallback\n";
} catch (Exception $e) {
    echo "All storage backends failed: " . $e->getMessage() . "\n";
}


```