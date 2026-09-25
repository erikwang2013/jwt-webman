<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

/*
 * 原生 PHP（无框架）配置模板：纯 getenv()，不依赖任何框架 helper。
 *
 *   $jwt = Erikwang2013\Jwt\JWTFactory::createFromFile(__DIR__ . '/config/jwt.php');
 */

return [
    'secret_key'     => getenv('JWT_SECRET_KEY') ?: '',
    'algorithm'      => getenv('JWT_ALGORITHM') ?: 'HS256',
    'issuer'         => getenv('JWT_ISSUER') ?: '',
    'audience'       => getenv('JWT_AUDIENCE') ?: '',
    'leeway'         => (int) (getenv('JWT_LEEWAY') ?: 0),
    'default_expire' => (int) (getenv('JWT_DEFAULT_EXPIRE') ?: 3600),
    'refresh_expire' => (int) (getenv('JWT_REFRESH_EXPIRE') ?: 7200),

    'storage' => [
        // 存储类型：file / redis / database / memcached
        'type'     => getenv('JWT_STORAGE_TYPE') ?: 'file',
        // 键前缀，隔离多应用与多环境
        'prefix'   => getenv('JWT_STORAGE_PREFIX') ?: 'jwt_blacklist:',
        // file 驱动：黑名单目录，留空用系统临时目录
        'path'     => getenv('JWT_STORAGE_PATH') ?: null,
        // database 驱动：表名
        'table_name'        => getenv('JWT_STORAGE_TABLE') ?: 'jwt_blacklist',
        // database 驱动：表已由迁移脚本建好时可关闭自动建表（数据库账号无 DDL 权限时须关闭）
        'auto_create_table' => filter_var(getenv('JWT_STORAGE_AUTO_CREATE_TABLE') ?: '1', FILTER_VALIDATE_BOOLEAN),
        // file 驱动：每次写入触发过期清理的概率，0 表示关闭并交给定时任务
        'gc_probability'    => getenv('JWT_STORAGE_GC_PROBABILITY') === false
            ? 0.1
            : (float) getenv('JWT_STORAGE_GC_PROBABILITY'),
        // 存储故障时：false（默认）拒绝所有令牌；true 放行并记 error 日志
        'fail_open'         => filter_var(getenv('JWT_STORAGE_FAIL_OPEN') ?: '0', FILTER_VALIDATE_BOOLEAN),
    ],

    'advanced' => [
        'retry_attempts'   => (int) (getenv('JWT_ADVANCED_RETRY_ATTEMPTS') ?: 3),
        'retry_delay'      => (int) (getenv('JWT_ADVANCED_RETRY_DELAY') ?: 100),
        'auto_cleanup'     => filter_var(getenv('JWT_AUTO_CLEANUP') ?: '0', FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval' => (int) (getenv('JWT_CLEANUP_INTERVAL') ?: 3600),
    ],
];
