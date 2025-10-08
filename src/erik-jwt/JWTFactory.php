<?php
// src/JWTFactory.php

namespace ErikJwt;

use support\Redis;
use support\Db;
use Memcached;
use Exception;

class JWTFactory
{

    public static function getConfig()
    {
        return config('plugin.erikwang2013.jwt.jwt');
    }

    public static function createFromConfig(): JWT
    {
        $config = self::getConfig();

        $secretKey = $config['secret_key'];
        $algorithm = $config['algorithm'];
        $issuer = $config['issuer'];
        $audience = $config['audience'];
        $leeway = $config['leeway'];

        $tokenStorage = self::createTokenStorage($config);

         // 应用高级配置：重试机制
        $advancedConfig = $config->get('advanced', []);
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

    private static function createTokenStorage(): TokenStorageInterface
    {
        $config = self::getConfig();
        $storageConfig = $config['storage'];
        $type = $storageConfig['type'] ?? 'file';

        switch ($type) {
            case 'redis':
                return self::createRedisStorage($storageConfig['config'] ?? []);
            case 'database':
                return self::createDatabaseStorage($storageConfig['config'] ?? []);
            case 'memcached':
                return self::createMemcachedStorage($storageConfig['config'] ?? []);
            case 'file':
            default:
                return self::createFileStorage($storageConfig['config'] ?? []);
        }
    }

    private static function createRedisStorage(array $config): RedisTokenStorage
    {

        try {
            $database = $config['database'] ?? 0;
            Redis::select($database);
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
