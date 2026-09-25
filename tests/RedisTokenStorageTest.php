<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\RedisTokenStorage;
use PHPUnit\Framework\TestCase;

class RedisTokenStorageTest extends TestCase
{
    private function makeRedis($pong, $existsResult = 0, $existsRaw = null)
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
        $redis = $this->makeRedis('PONG', 0);
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertFalse($storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6'));
    }

    public function testIsBlacklistedAcceptsIntReturn(): void
    {
        $redis = $this->makeRedis('+PONG', 0, 1);
        $storage = new RedisTokenStorage(fn () => $redis);
        $this->assertTrue($storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6'));
    }

    public function testExistsFalseThrowsInsteadOfFailingOpen(): void
    {
        // phpredis 超时/链路错误时 exists() 返回 false，与"0 个键"无法区分，
        // 当成"未拉黑"会放行已拉黑的令牌，必须抛错交给 fail_open 决策
        $storage = new RedisTokenStorage(fn () => $this->makeRedis('PONG', false));

        try {
            $storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6');
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('no reply', $e->getMessage());
        }
    }

    public function testNonHexJtiIsHashedIntoKey(): void
    {
        $redis = $this->makeRedis('PONG');
        $storage = new RedisTokenStorage(fn () => $redis, 'bl:');
        $jti = '550e8400-e29b-41d4-a716-446655440000';

        $storage->blacklist($jti, time() + 3600);
        $this->assertSame('bl:' . hash('sha256', $jti), $redis->lastSetexKey);
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
        $this->assertSame(1, $redis->pingCalls);
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
        $storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600);
        $storage->reconnect();
        $this->assertSame(1, $redis->closeCalls);
    }
}
