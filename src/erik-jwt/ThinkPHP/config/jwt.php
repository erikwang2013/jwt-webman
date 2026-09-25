<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

return [
    'secret_key'     => env('JWT.SECRET_KEY', ''),
    'algorithm'      => env('JWT.ALGORITHM', 'HS256'),
    'issuer'         => env('JWT.ISSUER', ''),
    'audience'       => env('JWT.AUDIENCE', ''),
    'leeway'         => (int) env('JWT.LEEWAY', 0),
    'default_expire' => (int) env('JWT.DEFAULT_EXPIRE', 3600),
    'refresh_expire' => (int) env('JWT.REFRESH_EXPIRE', 7200),
    'storage' => [
        'type'     => env('JWT.STORAGE_TYPE', 'file'),
        'prefix'   => env('JWT.STORAGE_PREFIX', 'jwt_blacklist:'),
        // file 驱动：黑名单目录，留空用系统临时目录
        'path'     => env('JWT.STORAGE_PATH'),
        // database 驱动：表名
        'table_name'        => env('JWT.STORAGE_TABLE', 'jwt_blacklist'),
        // database 驱动：表已由迁移脚本建好时可关闭自动建表（数据库账号无 DDL 权限时须关闭）
        'auto_create_table' => filter_var(env('JWT.STORAGE_AUTO_CREATE_TABLE', true), FILTER_VALIDATE_BOOLEAN),
        // file 驱动：每次写入触发过期清理的概率，0 表示关闭并交给定时任务
        'gc_probability'    => (float) env('JWT.STORAGE_GC_PROBABILITY', 0.1),
        // 存储故障时：false（默认）拒绝所有令牌；true 放行并记 error 日志
        'fail_open'         => filter_var(env('JWT.STORAGE_FAIL_OPEN', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT.ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT.ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT.AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT.CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => [],
    ],
];
