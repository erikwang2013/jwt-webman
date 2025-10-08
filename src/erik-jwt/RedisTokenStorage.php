<?php
// src/RedisTokenStorage.php

namespace ErikJwt;

use support\Redis;
use Exception;

class RedisTokenStorage implements TokenStorageInterface
{
    private $redis;
    private $prefix;
    private $connected = false;

    public function __construct(Redis $redis, string $prefix = 'jwt_blacklist:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->checkConnection();
    }

    /**
     * 检查Redis连接
     */
    private function checkConnection(): void
    {
        try {
            $this->connected = $this->redis->ping() === true;
        } catch (Exception $e) {
            $this->connected = false;
            throw JWTException::storageError('Redis connection failed: ' . $e->getMessage());
        }
    }

    /**
     * 确保连接正常
     */
    private function ensureConnection(): void
    {
        if (!$this->connected) {
            $this->checkConnection();
        }
        
        if (!$this->connected) {
            throw JWTException::storageError('Redis is not connected');
        }
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        $this->ensureConnection();
        
        try {
            $now = time();
            $ttl = $expireTime - $now;
            
            if ($ttl <= 0) {
                return true; // 已经过期的令牌不需要加入黑名单
            }

            $key = $this->prefix . $jti;
            $result = $this->redis->setex($key, $ttl, '1');
            
            if ($result === false) {
                throw JWTException::storageError('Failed to blacklist token in Redis');
            }
            
            return $result;
        } catch (Exception $e) {
            throw JWTException::storageError('Redis blacklist operation failed: ' . $e->getMessage());
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        $this->ensureConnection();
        
        try {
            $key = $this->prefix . $jti;
            $exists = $this->redis->exists($key);
            
            // 处理不同版本的Redis exists方法返回值
            if (is_bool($exists)) {
                return $exists;
            }
            
            // Redis >= 5.0.0 返回整数
            return (bool) $exists;
        } catch (Exception $e) {
            throw JWTException::storageError('Redis blacklist check failed: ' . $e->getMessage());
        }
    }

    public function cleanup(): bool
    {
        // Redis会自动过期，不需要手动清理
        return true;
    }

    /**
     * 获取Redis连接状态
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * 重新连接Redis
     */
    public function reconnect(): bool
    {
        try {
            $this->redis->close();
            $this->connected = false;
            $this->checkConnection();
            return $this->connected;
        } catch (Exception $e) {
            throw JWTException::storageError('Redis reconnection failed: ' . $e->getMessage());
        }
    }
}