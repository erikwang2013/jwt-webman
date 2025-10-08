## 简介 ##
erikwang2013/jwt-webman是一款适配webman的jwt插件。
主要是适用分布式部署，用于适配webman，安装简单快捷。

## 作者博客 ##
[艾瑞可erik](https://erik.xyz)

## 安装 ##



Use [Composer](https://github.com/composer/composer):
```sh
composer require erikwang2013/jwt-webman
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
    
    //验证令牌状态
    $jwt->validate($token);

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

## 配置文件 ##

仅供参考，根据实际配置。

- file

```

<?php
// config/development.php

return [
    'secret_key' => 'dev-secret-key-change-in-production',
    'algorithm' => 'HS256',
    'issuer' => 'myapp-dev',
    'audience' => 'dev-users',
    'leeway' => 60,
    'default_expire' => 7200, // 2小时
    
    'storage' => [
        'type' => 'file',
        'config' => [
            'path' => __DIR__ . '/../storage/jwt',
            'gc_probability' => 0.01
        ]
    ],
    
    'advanced' => [
        'retry_attempts' => 1,
        'auto_cleanup' => true
    ]
];

```

- redis

```

<?php
// config/production.php

return [
    'secret_key' => getenv('JWT_SECRET_KEY'), // 从环境变量读取
    'algorithm' => 'HS256',
    'issuer' => 'myapp-prod',
    'audience' => 'prod-users',
    'leeway' => 30,
    'default_expire' => 1800, // 30分钟
    'refresh_expire' => 2592000, // 30天
    
    'storage' => [
        'type' => 'redis',
        'config' => [
            'database' => 1,
            'prefix' => 'prod:jwt:blacklist:',
            'timeout' => 1.0,
            'read_timeout' => 1.0,
            'persistent' => true,
            'persistent_id' => 'jwt_pool'
        ]
    ],
    
    'advanced' => [
        'retry_attempts' => 3,
        'retry_delay' => 200,
        'auto_cleanup' => true,
        'cleanup_interval' => 1800
    ]
];

```

- db

```
<?php
// config/production_database.php

return [
    'secret_key' => getenv('JWT_SECRET_KEY'),
    'algorithm' => 'HS256',
    'default_expire' => 3600,
    
    'storage' => [
        'type' => 'database',
        'config' => [
            'dsn' => getenv('DATABASE_DSN'),
            'table_name' => 'user_token_blacklist',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        ]
    ],
    
    'advanced' => [
        'retry_attempts' => 2,
        'auto_cleanup' => true,
        'cleanup_interval' => 3600
    ]
];

```

- 集群配置

```

<?php
// config/cluster.php

return [
    'secret_key' => getenv('JWT_SECRET_KEY'),
    'algorithm' => 'HS256',
    'issuer' => 'cluster-app',
    'leeway' => 10, // 集群环境时间同步较好，容差可以小一些
    
    'storage' => [
        'type' => 'redis',
        'config' => [
            'prefix' => 'cluster:jwt:',
            'timeout' => 0.5, // 集群环境降低超时时间
            'read_timeout' => 0.5
        ]
    ],
    
    'advanced' => [
        'retry_attempts' => 2,
        'retry_delay' => 50, // 集群环境重试延迟更短
        'auto_cleanup' => true
    ]
];

```


- 安全配置建议

```

<?php
// 安全配置示例
return [
    'secret_key' => bin2hex(random_bytes(32)), // 生成32字节随机密钥
    'algorithm' => 'HS256', // 使用安全的哈希算法
    'default_expire' => 900, // 短期令牌，15分钟
    'refresh_expire' => 604800, // 长期刷新令牌，7天
    
    'storage' => [
        'type' => 'redis',
        'config' => [
            'password' => 'strong-redis-password', // Redis密码
            'database' => 5, // 使用专用数据库
            'prefix' => 'secure:jwt:' // 唯一前缀
        ]
    ]
];

```

- 性能优化建议

```

<?php
// 高性能配置
return [
    'secret_key' => 'your-secret-key',
    'leeway' => 5, // 减少时间容差
    
    'storage' => [
        'type' => 'redis',
        'config' => [
            'persistent' => true, // 使用持久连接
            'timeout' => 0.1, // 降低超时时间
            'read_timeout' => 0.1
        ]
    ],
    
    'advanced' => [
        'retry_attempts' => 1, // 减少重试次数
        'retry_delay' => 10 // 减少重试延迟
    ]
];

```