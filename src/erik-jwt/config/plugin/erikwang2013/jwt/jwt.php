<?php

return [
    'enable' => true,
    'secret_key' => 'your-very-secret-key',  //签名密钥
    'algorithm' => 'HS256',  //签名算法：HS256, HS384, HS512, RS256等
    'issuer' => 'erik.xyz',   //签发者标识，用于验证令牌来源
    'audience' => 'erik.xyz',  //受众标识，用于验证令牌目标
    'leeway' => 60,            //时间容差（秒），用于处理时钟偏差
    'default_expire' => 3600, //默认令牌过期时间（秒）
    'refresh_expire' => 7200,  //刷新令牌过期时间（秒）
    'storage' => [
        'type' => 'redis',  //存储类型：redis, database, memcached, file
        'database' => 1,
        'prefix' => 'jwt_blacklist:'
    ],
    'advanced' => [
        'retry_attempts' => 3,   //操作失败重试次数
        'retry_delay' => 100,    //重试延迟（毫秒）
        'auto_cleanup' => true,  //是否自动清理过期条目
        'cleanup_interval' => 3600   //自动清理间隔（秒）
    ]
];
