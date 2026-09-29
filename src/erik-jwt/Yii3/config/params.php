<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

/**
 * Yii3 默认参数。composer 的 config-plugin 会把本文件并入应用 params，
 * 应用在 config/params.php 里用 'jwt' 键覆盖任意一项即可：
 *
 *     // config/params.php
 *     return [
 *         'jwt' => ['secret_key' => $_ENV['JWT_SECRET_KEY'], 'storage' => ['type' => 'redis', 'connection' => RedisInterface::class]],
 *     ];
 *
 * 用 getenv() 读环境变量：真实环境变量、或任何走 putenv() 的 .env 加载器都能生效。
 */

return [
    'jwt' => [
        'secret_key'     => getenv('JWT_SECRET_KEY') ?: '',
        'algorithm'      => getenv('JWT_ALGORITHM') ?: 'HS256',
        'issuer'         => getenv('JWT_ISSUER') ?: '',
        'audience'       => getenv('JWT_AUDIENCE') ?: '',
        'leeway'         => (int) (getenv('JWT_LEEWAY') ?: 0),
        'default_expire' => (int) (getenv('JWT_DEFAULT_EXPIRE') ?: 3600),
        'refresh_expire' => (int) (getenv('JWT_REFRESH_EXPIRE') ?: 7200),
        'storage' => [
            // file（默认）/ redis / database / memcached
            'type'     => getenv('JWT_STORAGE_TYPE') ?: 'file',
            'prefix'   => getenv('JWT_STORAGE_PREFIX') ?: 'jwt_blacklist:',
            // file 驱动：黑名单目录，留空用系统临时目录
            'path'     => getenv('JWT_STORAGE_PATH') ?: null,
            // redis / database 驱动：连接对象在容器中的服务 id。
            // 本包不硬编码 yiisoft/db、yiisoft/redis 的接口名，由应用显式指定，
            // 例如 RedisInterface::class 或 ConnectionInterface::class。
            'connection' => getenv('JWT_STORAGE_CONNECTION') ?: null,
            // database 驱动：表名
            'table_name'        => getenv('JWT_STORAGE_TABLE') ?: 'jwt_blacklist',
            // database 驱动：表已由迁移脚本建好时可关闭自动建表（数据库账号无 DDL 权限时须关闭）
            'auto_create_table' => filter_var(getenv('JWT_STORAGE_AUTO_CREATE_TABLE') ?: '1', FILTER_VALIDATE_BOOLEAN),
            // file 驱动：每次写入触发过期清理的概率，0 表示关闭并交给定时任务
            'gc_probability'    => (float) (getenv('JWT_STORAGE_GC_PROBABILITY') ?: 0.1),
            // 存储故障时：false（默认）拒绝所有令牌；true 放行并记 error 日志
            'fail_open'         => filter_var(getenv('JWT_STORAGE_FAIL_OPEN') ?: '', FILTER_VALIDATE_BOOLEAN),
        ],
        'advanced' => [
            'retry_attempts'   => (int) (getenv('JWT_ADVANCED_RETRY_ATTEMPTS') ?: 3),
            'retry_delay'      => (int) (getenv('JWT_ADVANCED_RETRY_DELAY') ?: 100),
            'auto_cleanup'      => filter_var(getenv('JWT_AUTO_CLEANUP') ?: '', FILTER_VALIDATE_BOOLEAN),
            'cleanup_interval'  => (int) (getenv('JWT_CLEANUP_INTERVAL') ?: 3600),
        ],
        'middleware' => [
            // 跳过校验的路径，正则片段，如 ['api/login', 'api/health']
            'except' => [],
        ],
    ],
];
