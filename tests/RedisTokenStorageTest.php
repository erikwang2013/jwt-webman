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
            public $setexCalls = 0;
            public $pingCalls = 0;

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
                return true;
            }

            public function exists($key)
            {
                return $this->existsRaw !== null ? $this->existsRaw : $this->existsResult;
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
}
