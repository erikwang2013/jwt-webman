<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\MemcachedTokenStorage;
use PHPUnit\Framework\TestCase;

class MemcachedTokenStorageTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Memcached')) {
            $this->markTestSkipped('Memcached extension not available');
        }
    }

    private function makeMemcached($resultCode = null)
    {
        $memcached = $this->createMock(\Memcached::class);
        $calls = ['set' => 0, 'get' => 0];
        $data = [];
        $memcached->method('set')->willReturnCallback(
            function ($key, $value, $ttl) use (&$calls, &$data) {
                $calls['set']++;
                $data[$key] = $value;
                return true;
            }
        );
        $memcached->method('get')->willReturnCallback(
            function ($key) use (&$calls, &$data) {
                $calls['get']++;
                return $data[$key] ?? false;
            }
        );
        $memcached->method('getResultCode')->willReturnCallback(
            function () use ($resultCode, &$data) {
                if ($resultCode !== null) {
                    return $resultCode;
                }
                return empty($data) ? \Memcached::RES_NOTFOUND : \Memcached::RES_SUCCESS;
            }
        );
        $memcached->method('getResultMessage')->willReturn('fake memcached error');
        return [$memcached, $calls, $data];
    }

    private function jti(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function testBlacklistStoresWithPrefixAndTtl(): void
    {
        [$memcached, $calls, $data] = $this->makeMemcached();
        $storage = new MemcachedTokenStorage($memcached, 'bl:');
        $jti = $this->jti();
        $this->assertTrue($storage->blacklist($jti, time() + 3600));
        $this->assertSame(1, $calls['set']);
        $this->assertArrayHasKey('bl:' . $jti, $data);
    }

    public function testIsBlacklistedTrue(): void
    {
        [$memcached] = $this->makeMemcached();
        $storage = new MemcachedTokenStorage($memcached);
        $jti = $this->jti();
        $storage->blacklist($jti, time() + 3600);
        $this->assertTrue($storage->isBlacklisted($jti));
    }

    public function testIsBlacklistedFalseForMissingKey(): void
    {
        [$memcached] = $this->makeMemcached();
        $storage = new MemcachedTokenStorage($memcached);
        $this->assertFalse($storage->isBlacklisted($this->jti()));
    }

    public function testAlreadyExpiredTokenReturnsTrue(): void
    {
        [$memcached, $calls] = $this->makeMemcached();
        $storage = new MemcachedTokenStorage($memcached);
        $this->assertTrue($storage->blacklist($this->jti(), time() - 100));
        $this->assertSame(0, $calls['set']);
    }

    public function testCleanupReturnsTrue(): void
    {
        [$memcached] = $this->makeMemcached();
        $storage = new MemcachedTokenStorage($memcached);
        $this->assertTrue($storage->cleanup());
    }

    public function testInvalidJtiThrowsOnBlacklist(): void
    {
        [$memcached] = $this->makeMemcached();
        $storage = new MemcachedTokenStorage($memcached);
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
        [$memcached] = $this->makeMemcached();
        $storage = new MemcachedTokenStorage($memcached);
        try {
            $storage->isBlacklisted('not-hex!');
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
        }
    }

    public function testGetFailureCodeThrows(): void
    {
        [$memcached] = $this->makeMemcached(\Memcached::RES_FAILURE);
        $storage = new MemcachedTokenStorage($memcached);
        try {
            $storage->isBlacklisted($this->jti());
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('fake memcached error', $e->getMessage());
        }
    }

    public function testGetResultSuccessWithFalseValueNotBlacklisted(): void
    {
        $memcached = $this->createMock(\Memcached::class);
        $memcached->method('get')->willReturn(false);
        $memcached->method('getResultCode')->willReturn(\Memcached::RES_SUCCESS);
        $storage = new MemcachedTokenStorage($memcached);
        $this->assertFalse($storage->isBlacklisted($this->jti()));
    }
}
