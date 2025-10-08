<?php
// src/MemcachedTokenStorage.php

namespace ErikJwt;

use Memcached;
use Exception;

class MemcachedTokenStorage implements TokenStorageInterface
{
    private $memcached;
    private $prefix;

    public function __construct(Memcached $memcached, string $prefix = 'jwt_blacklist:')
    {
        $this->memcached = $memcached;
        $this->prefix = $prefix;
    }

    public function blacklist(string $jti, int $expireTime): bool
    {
        try {
            $now = time();
            $ttl = $expireTime - $now;
            
            if ($ttl <= 0) {
                return true;
            }

            $key = $this->prefix . $jti;
            return $this->memcached->set($key, '1', $ttl);
        } catch (Exception $e) {
            throw new JWTException('Memcached operation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        try {
            $key = $this->prefix . $jti;
            $result = $this->memcached->get($key);
            return $result !== false;
        } catch (Exception $e) {
            throw new JWTException('Memcached operation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cleanup(): bool
    {
        // Memcached会自动过期，不需要手动清理
        return true;
    }
}