<?php
// src/JWTFactory.php

namespace ErikJwt;

use support\Redis;
use PDO;
use Memcached;
use Exception;

class JWTFactory
{
    public static function createFromConfig(): JWT
    {
        $config = new Config();
        $secretKey = $config->get('secret_key');
        $algorithm = $config->get('algorithm');
        $issuer = $config->get('issuer');
        $audience = $config->get('audience');
        $leeway = $config->get('leeway');

        $tokenStorage = self::createTokenStorage($config);

        return new JWT($secretKey, $algorithm, $tokenStorage, $issuer, $audience, $leeway);
    }

    private static function createTokenStorage(Config $config): TokenStorageInterface
    {
        $storageConfig = $config->get('storage', []);
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
        $redis = new Redis();
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 2.5;
        $readTimeout = $config['read_timeout'] ?? $timeout;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        $persistent = $config['persistent'] ?? false;
        $persistentId = $config['persistent_id'] ?? null;

        // 设置连接选项
        $redis->setOption(Redis::OPT_READ_TIMEOUT, $readTimeout);
        $redis->setOption(Redis::OPT_TCP_NODELAY, true);

        if (isset($config['serializer'])) {
            $redis->setOption(Redis::OPT_SERIALIZER, $config['serializer']);
        }

        try {
            if ($persistent) {
                $persistentId = $persistentId ?? "jwt_{$host}_{$port}_{$database}";
                $connected = $redis->pconnect($host, $port, $timeout, $persistentId);
            } else {
                $connected = $redis->connect($host, $port, $timeout);
            }

            if (!$connected) {
                throw new Exception('Failed to connect to Redis server');
            }

            if ($password) {
                $authResult = $redis->auth($password);
                if (!$authResult) {
                    throw new Exception('Redis authentication failed');
                }
            }

            if ($database > 0) {
                $selectResult = $redis->select($database);
                if (!$selectResult) {
                    throw new Exception('Failed to select Redis database');
                }
            }

            // 测试连接
            $pingResult = $redis->ping();
            if ($pingResult !== true && $pingResult !== '+PONG') {
                throw new Exception('Redis ping failed');
            }

            $prefix = $config['prefix'] ?? 'jwt_blacklist:';

            return new RedisTokenStorage($redis, $prefix);
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
