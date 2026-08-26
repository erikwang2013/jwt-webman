<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\RedisTokenStorage;
use PHPUnit\Framework\TestCase;

class RedisTokenStorageTest extends TestCase
{
    private function makeRedis($pong, bool $existsResult = false, $existsRaw = null)
    {
        return new class($pong, $existsResult, $existsRaw) {
            public $pong;
            public $existsResult;
            public $existsRaw;
            public $setexResult = true;
            public $setexCalls = 0;
            public $pingCalls = 0;
            public $closeCalls = 0;
            public $lastSetexKey;
            public $lastExistsKey;

            public function __construct($pong, $existsResult, $existsRaw)
            {
                $this->pong = $pong;
                $this->existsResult = $existsResult;
                $this->existsRaw = $existsRaw;
            }

            public function ping()
            {
                $this->pingCalls++;
                return $this->pong;
            }

            public function setex($key, $ttl, $value)
            {
                $this->setexCalls++;
                $this->lastSetexKey = $key;
                return $this->setexResult;
            }

            public function exists($key)
            {
                $this->lastExistsKey = $key;
                return $this->existsRaw !== null ? $this->existsRaw : $this->existsResult;
            }

            public function close()
            {
                $this->closeCalls++;
                return true;
            }
        };
    }

    public function testBlacklistWorksWithPongPing(): void
    {
        $redis = $this->makeRedis('PONG');
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertTrue($storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600));
        $this->assertSame(1, $redis->setexCalls);
    }

    public function testBlacklistWorksWithBoolPing(): void
    {
        $redis = $this->makeRedis(true);
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertTrue($storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600));
        $this->assertSame(1, $redis->setexCalls);
    }

    public function testIsBlacklistedTrue(): void
    {
        $redis = $this->makeRedis('PONG', true);
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertTrue($storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6'));
    }

    public function testIsBlacklistedFalse(): void
    {
        $redis = $this->makeRedis('PONG', false);
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertFalse($storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6'));
    }

    public function testIsBlacklistedAcceptsIntReturn(): void
    {
        $redis = $this->makeRedis('+PONG', false, 1);
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertTrue($storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6'));
    }

    public function testInvalidJtiThrowsOnBlacklist(): void
    {
        $storage = new RedisTokenStorage(fn () => $this->makeRedis('PONG'));
        try {
            $storage->blacklist('not-hex!', time() + 3600);
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('Invalid JTI format', $e->getMessage());
        }
    }

    public function testInvalidJtiThrowsOnIsBlacklisted(): void
    {
        $storage = new RedisTokenStorage(fn () => $this->makeRedis('PONG'));
        try {
            $storage->isBlacklisted('not-hex!');
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('Invalid JTI format', $e->getMessage());
        }
    }

    public function testFailedPingThrowsOnIsBlacklisted(): void
    {
        $redis = $this->makeRedis(false);
        $storage = new RedisTokenStorage(fn () => $redis);
        try {
            $storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6');
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
        }
        $this->assertSame(2, $redis->pingCalls);
    }

    public function testFailedPingThrowsOnBlacklist(): void
    {
        $redis = $this->makeRedis(false);
        $storage = new RedisTokenStorage(fn () => $redis);
        try {
            $storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600);
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
        }
        $this->assertSame(0, $redis->setexCalls);
    }

    public function testCleanupReturnsTrue(): void
    {
        $storage = new RedisTokenStorage(fn () => $this->makeRedis('PONG'));
        $this->assertTrue($storage->cleanup());
    }

    public function testIsConnectedState(): void
    {
        $redis = $this->makeRedis('PONG');
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertFalse($storage->isConnected());
        $storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600);
        $this->assertTrue($storage->isConnected());
    }

    public function testReconnectSuccess(): void
    {
        $redis = $this->makeRedis('PONG');
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertTrue($storage->reconnect());
        $this->assertTrue($storage->isConnected());
    }

    public function testReconnectFailureReturnsFalse(): void
    {
        $redis = $this->makeRedis(false);
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertFalse($storage->reconnect());
    }

    public function testReconnectResolverExceptionThrows(): void
    {
        $storage = new RedisTokenStorage(function () {
            throw new \RuntimeException('redis down');
        });
        try {
            $storage->reconnect();
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('redis down', $e->getMessage());
        }
    }

    public function testBlacklistExpiredTokenReturnsTrueWithoutSetex(): void
    {
        $redis = $this->makeRedis('PONG');
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertTrue($storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() - 100));
        $this->assertSame(0, $redis->setexCalls);
    }

    public function testSetexFalseThrows(): void
    {
        $redis = $this->makeRedis('PONG');
        $redis->setexResult = false;
        $storage = new RedisTokenStorage(fn () => $redis);
        try {
            $storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600);
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
        }
    }

    public function testPrefixIsAppliedToKey(): void
    {
        $redis = $this->makeRedis('PONG');
        $jti = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6';
        $storage = new RedisTokenStorage(fn () => $redis, 'bl:');
        $storage->blacklist($jti, time() + 3600);
        $this->assertSame('bl:' . $jti, $redis->lastSetexKey);
        $storage->isBlacklisted($jti);
        $this->assertSame('bl:' . $jti, $redis->lastExistsKey);
    }

    public function testPingExceptionThrowsStorageError(): void
    {
        $redis = new class {
            public function ping()
            {
                throw new \RuntimeException('connection refused');
            }
        };
        $storage = new RedisTokenStorage(fn () => $redis);
        try {
            $storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600);
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('connection refused', $e->getMessage());
        }
    }

    public function testExistsExceptionIsWrapped(): void
    {
        $redis = new class {
            public function ping()
            {
                return 'PONG';
            }

            public function exists($key)
            {
                throw new \RuntimeException('redis down');
            }
        };
        $storage = new RedisTokenStorage(fn () => $redis);
        try {
            $storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6');
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('redis down', $e->getMessage());
        }
    }

    public function testReconnectClosesExistingConnection(): void
    {
        $redis = $this->makeRedis('PONG');
        $storage = new RedisTokenStorage(fn () => $redis);
        $storage->reconnect();
        $this->assertSame(1, $redis->closeCalls);
    }
}
