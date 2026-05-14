<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2025 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace ErikJwt;

use support\Db;
use Memcached;
use Exception;

class JWTFactory
{

    public static function getConfig(): array
    {
        return config('plugin.erikwang2013.jwt.jwt') ?: [];
    }

    /**
     * 从配置创建 JWT 实例。可传入 Config 或使用全局配置。
     */
    public static function createFromConfig(?Config $config = null): JWT
    {
        $configArray = $config !== null ? $config->toArray() : self::getConfig();
        $config = $configArray;

        $secretKey = $config['secret_key'] ?? '';
        if (empty($secretKey) || strlen($secretKey) < 16) {
            throw JWTException::configError('Secret key must be at least 16 characters');
        }
        $algorithm = $config['algorithm'] ?? 'HS256';
        $issuer = $config['issuer'] ?? '';
        $audience = $config['audience'] ?? '';
        $leeway = (int) ($config['leeway'] ?? 0);

        $tokenStorage = self::createTokenStorage($config['storage'] ?? []);

         // 应用高级配置：重试机制
        $advancedConfig = $config['advanced'] ?? [];
        $retryAttempts = $advancedConfig['retry_attempts'] ?? 3;
        $retryDelay = $advancedConfig['retry_delay'] ?? 100;
        
        if ($retryAttempts > 1) {
            $tokenStorage = new RetryTokenStorage($tokenStorage, $retryAttempts, $retryDelay);
        }

        $jwt = new JWT($secretKey, $algorithm, $tokenStorage, $issuer, $audience, $leeway);
        // 设置自动清理（如果启用）
        $autoCleanup = $advancedConfig['auto_cleanup'] ?? false;
        if ($autoCleanup) {
            self::setupAutoCleanup($jwt, $advancedConfig);
        }

        return $jwt;
    }

    /**
     * 合并 storage 顶层项到 config，使默认配置中 storage.database / storage.prefix 等生效。
     */
    private static function createTokenStorage(array $storageConfig): TokenStorageInterface
    {
        $merged = array_merge(
            ['database' => 0, 'prefix' => 'jwt_blacklist:', 'path' => null, 'table_name' => 'jwt_blacklist', 'servers' => []],
            $storageConfig,
            $storageConfig['config'] ?? []
        );
        $type = $storageConfig['type'] ?? 'file';

        switch ($type) {
            case 'redis':
                return self::createRedisStorage($merged);
            case 'database':
                return self::createDatabaseStorage($merged);
            case 'memcached':
                return self::createMemcachedStorage($merged);
            case 'file':
            default:
                return self::createFileStorage($merged);
        }
    }

    private static function createRedisStorage(array $config): RedisTokenStorage
    {
        try {
            $prefix = $config['prefix'] ?? 'jwt_blacklist:';
            return new RedisTokenStorage($prefix);
        } catch (Exception $e) {
            throw JWTException::storageError('Redis initialization failed: ' . $e->getMessage());
        }
    }

    private static function createDatabaseStorage(array $config): DatabaseTokenStorage
    {

        $tableName = $config['table_name'] ?? 'jwt_blacklist';
        Db::table($tableName);
        return new DatabaseTokenStorage($tableName);
    }

    private static function createMemcachedStorage(array $config): MemcachedTokenStorage
    {
        $memcached = new Memcached();
        $servers = $config['servers'] ?? [['127.0.0.1', 11211]];

        $memcached->addServers($servers);

        if (isset($config['options'])) {
            $memcached->setOptions($config['options']);
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
     */
    private static function setupAutoCleanup(JWT $jwt, array $advancedConfig): void
    {
        $cleanupInterval = $advancedConfig['cleanup_interval'] ?? 3600;
        
        // 注册 shutdown 函数进行清理
        register_shutdown_function(function () use ($jwt, $cleanupInterval) {
            static $lastCleanup = 0;
            $now = time();
            
            // 检查是否需要清理（避免每次请求都清理）
            if ($now - $lastCleanup >= $cleanupInterval) {
                try {
                    $jwt->cleanup();
                    $lastCleanup = $now;
                } catch (Exception $e) {
                    // 忽略清理错误，不影响主要功能
                    error_log("JWT auto cleanup failed: " . $e->getMessage());
                }
            }
        });
    }

}
