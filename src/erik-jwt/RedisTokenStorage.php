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
        $now = time();
        $ttl = $expireTime - $now;
        if ($ttl <= 0) {
            return true;
        }

        try {
            $result = $this->redis()->setex($this->key($jti), $ttl, '1');
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
        try {
            $result = $this->redis()->exists($this->key($jti));
            // phpredis 在超时/链路错误时返回 false 而不是抛异常，(bool) 后与"0 个键"无法区分，
            // 会把已拉黑的令牌放行，且绕过 storage.fail_open 的 fail-closed 策略
            if ($result === false) {
                throw JWTException::storageError('Redis blacklist check failed: no reply from server');
            }

            return $result > 0;
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

    /**
     * 黑名单键：十六进制 jti（本库签发的都是）原样使用，其他格式（如迁移过来的 UUID）
     * 取 sha256，保证键安全且不改变已有键的命名。
     */
    private function key(string $jti): string
    {
        return $this->prefix . (ctype_xdigit($jti) ? $jti : hash('sha256', $jti));
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
