<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt;

use Memcached;
use PDO;
use Psr\Log\LoggerInterface;

class JWTFactory
{

    /**
     * 从配置创建 JWT 实例。
     */
    public static function createFromConfig(
        array $config,
        ?LoggerInterface $logger = null,
        array $connections = []
    ): JWT {
        $tokenStorage = self::createTokenStorage($config, $connections);
        $advancedConfig = $config['advanced'] ?? [];
        $retryAttempts = (int)($advancedConfig['retry_attempts'] ?? 3);
        $retryDelay    = (int)($advancedConfig['retry_delay'] ?? 100);

        if ($retryAttempts > 1) {
            $tokenStorage = new RetryTokenStorage($tokenStorage, $retryAttempts, $retryDelay);
        }

        $config['_token_storage'] = $tokenStorage;
        $jwt = new JWT($config, $logger);

        $autoCleanup = $advancedConfig['auto_cleanup'] ?? false;
        if ($autoCleanup) {
            $seed = ($config['storage']['prefix'] ?? 'jwt_blacklist:') . ($config['storage']['path'] ?? '');
            self::setupAutoCleanup($jwt, $advancedConfig, $seed);
        }

        return $jwt;
    }

    /**
     * 从 PHP 配置文件创建 JWT 实例（原生 PHP 项目入口）。
     *
     * 配置文件直接 return 一个数组即可，模板见 src/erik-jwt/Native/config/jwt.php。
     */
    public static function createFromFile(
        string $configFile,
        ?LoggerInterface $logger = null,
        array $connections = []
    ): JWT {
        return self::createFromConfig(Config::fromFile($configFile)->toArray(), $logger, $connections);
    }

    /**
     * 合并 storage 顶层项到 config，使默认配置中 storage.database / storage.prefix 等生效。
     */
    private static function createTokenStorage(array $config, array $connections): TokenStorageInterface
    {
        $merged = array_merge(
            ['database' => 0, 'prefix' => 'jwt_blacklist:', 'path' => null, 'table_name' => 'jwt_blacklist', 'servers' => []],
            $config['storage'] ?? [],
            $config['storage']['config'] ?? []
        );
        $type = $merged['type'] ?? 'file';

        switch ($type) {
            case 'redis':
                return self::createRedisStorage($merged, $connections);
            case 'database':
                return self::createDatabaseStorage($merged, $connections);
            case 'memcached':
                return self::createMemcachedStorage($merged, $connections);
            case 'file':
            default:
                return self::createFileStorage($merged);
        }
    }

    private static function createRedisStorage(array $config, array $connections): RedisTokenStorage
    {
        $redisResolver = $connections['redis'] ?? null;
        if (!$redisResolver || !is_callable($redisResolver)) {
            throw JWTException::storageError('Redis resolver callable required when storage type is redis');
        }
        $prefix = $config['prefix'] ?? 'jwt_blacklist:';
        return new RedisTokenStorage($redisResolver, $prefix);
    }

    private static function createDatabaseStorage(array $config, array $connections): DatabaseTokenStorage
    {
        $pdo = $connections['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw JWTException::storageError('PDO instance required when storage type is database');
        }
        $tableName  = $config['table_name'] ?? 'jwt_blacklist';
        $autoCreate = (bool) ($config['auto_create_table'] ?? true);
        return new DatabaseTokenStorage($pdo, $tableName, $autoCreate);
    }

    private static function createMemcachedStorage(array $config, array $connections): MemcachedTokenStorage
    {
        $memcached = $connections['memcached'] ?? null;
        if (!$memcached instanceof Memcached) {
            if (!class_exists(\Memcached::class)) {
                throw JWTException::storageError('ext-memcached is required for memcached storage');
            }
            $memcached = new Memcached();
            // 默认合并里带了 'servers' => []，用 ?? 的话空数组会盖掉默认值 → addServers([]) 后不可用
            $servers = $config['servers'] ?: [['127.0.0.1', 11211]];
            $memcached->addServers($servers);
            if (isset($config['options'])) {
                $memcached->setOptions($config['options']);
            }
        }
        $prefix = $config['prefix'] ?? 'jwt_blacklist:';
        return new MemcachedTokenStorage($memcached, $prefix);
    }

    private static function createFileStorage(array $config): FileTokenStorage
    {
        $storagePath = $config['path'] ?? null;
        $gcProbability = $config['gc_probability'] ?? 0.1;

        $storage = new FileTokenStorage($storagePath);

        // 设置垃圾回收概率
        if (method_exists($storage, 'setGcProbability')) {
            $storage->setGcProbability($gcProbability);
        }

        return $storage;
    }

    /**
     * 设置自动清理
     *
     * 闭包内的 static 变量在 PHP-FPM 下每个请求都会重置（闭包是新建的），
     * 用它节流等于每个请求都清理一次，因此改用时间戳文件跨请求记录上次执行时间。
     */
    private static function setupAutoCleanup(JWT $jwt, array $advancedConfig, string $seed): void
    {
        $cleanupInterval = (int) ($advancedConfig['cleanup_interval'] ?? 3600);
        $marker = sys_get_temp_dir() . '/jwt_cleanup_' . md5($seed) . '.ts';

        register_shutdown_function(function () use ($jwt, $cleanupInterval, $marker) {
            if (!self::cleanupDue($marker, $cleanupInterval)) {
                return;
            }

            @file_put_contents($marker, (string) time(), LOCK_EX);

            try {
                $jwt->cleanup();
            } catch (\Exception $e) {
                error_log("JWT auto cleanup failed: " . $e->getMessage());
            }
        });
    }

    /**
     * 距离上次清理是否已超过间隔（0 或负数表示每次都清理）。
     */
    private static function cleanupDue(string $marker, int $interval): bool
    {
        if ($interval <= 0) {
            return true;
        }

        $last = is_file($marker) ? (int) @file_get_contents($marker) : 0;

        return (time() - $last) >= $interval;
    }

}
