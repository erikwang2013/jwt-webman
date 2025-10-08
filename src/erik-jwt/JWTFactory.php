<?php
// src/JWTFactory.php

namespace ErikJwt;

use support\Redis;
use PDO;
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

        return new JWT($secretKey, $algorithm, $tokenStorage, $issuer, $audience, $leeway);
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
        $dsn = $config['dsn'] ?? 'mysql:host=127.0.0.1;dbname=test;charset=utf8mb4';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);
        $tableName = $config['table_name'] ?? 'jwt_blacklist';

        return new DatabaseTokenStorage($pdo, $tableName);
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
        return new FileTokenStorage($storagePath);
    }

    /**
     * 创建简单的JWT实例（用于快速开始）
     */
    public static function createSimple(string $secretKey, string $storageType = 'file'): JWT
    {
        $config = new Config([
            'secret_key' => $secretKey,
            'storage' => [
                'type' => $storageType
            ]
        ]);

        return self::createFromConfig($config);
    }
}
