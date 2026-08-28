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

class RedisTokenStorage implements TokenStorageInterface
{
    private $prefix;
    private $redisResolver;
    private $redis = null;

    public function __construct(callable $redisResolver, string $prefix = 'jwt_blacklist:')
    {
        $this->prefix = $prefix;
        $this->redisResolver = $redisResolver;
    }

    private function redis()
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        try {
            $redis = ($this->redisResolver)();
            $pong  = $redis->ping();
        } catch (\Throwable $e) {
            throw JWTException::storageError('Redis connection failed: ' . $e->getMessage());
        }

        if ($pong !== true && $pong !== 'PONG' && $pong !== '+PONG') {
            throw JWTException::storageError('Redis connection failed: unexpected ping response');
        }

        return $this->redis = $redis;
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        if (!ctype_xdigit($jti)) {
            throw JWTException::storageError('Invalid JTI format');
        }

        $now = time();
        $ttl = $expireTime - $now;
        if ($ttl <= 0) {
            return true;
        }

        try {
            $result = $this->redis()->setex($this->prefix . $jti, $ttl, '1');
            if ($result === false) {
                throw JWTException::storageError('Failed to blacklist token in Redis');
            }
            return $result;
        } catch (\Throwable $e) {
            $this->redis = null;
            if ($e instanceof JWTException) {
                throw $e;
            }
            throw JWTException::storageError('Redis blacklist operation failed: ' . $e->getMessage());
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        if (!ctype_xdigit($jti)) {
            throw JWTException::storageError('Invalid JTI format');
        }

        try {
            return (bool) $this->redis()->exists($this->prefix . $jti);
        } catch (\Throwable $e) {
            $this->redis = null;
            if ($e instanceof JWTException) {
                throw $e;
            }
            throw JWTException::storageError('Redis blacklist check failed: ' . $e->getMessage());
        }
    }

    public function cleanup(): bool
    {
        // Redis会自动过期，不需要手动清理
        return true;
    }

    public function isConnected(): bool
    {
        return $this->redis !== null;
    }

    public function reconnect(): bool
    {
        try {
            if ($this->redis !== null && method_exists($this->redis, 'close')) {
                $this->redis->close();
            }
            $this->redis = null;
            $redis = ($this->redisResolver)();
            $pong  = $redis->ping();
            if ($pong !== true && $pong !== 'PONG' && $pong !== '+PONG') {
                return false;
            }
            $this->redis = $redis;
            return true;
        } catch (\Throwable $e) {
            $this->redis = null;
            throw JWTException::storageError('Redis reconnection failed: ' . $e->getMessage());
        }
    }
}
