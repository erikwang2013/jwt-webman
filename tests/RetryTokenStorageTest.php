<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\RetryTokenStorage;
use Erikwang2013\Jwt\TokenStorageInterface;
use PHPUnit\Framework\TestCase;

class RetryTokenStorageTest extends TestCase
{
    public function testSuccessOnFirstAttempt(): void
    {
        $inner = new class implements TokenStorageInterface {
            public $calls = 0;
            public function blacklist(string $jti, int $expireTime): bool { $this->calls++; return true; }
            public function isBlacklisted(string $jti): bool { $this->calls++; return false; }
            public function cleanup(): bool { $this->calls++; return true; }
        };
        $storage = new RetryTokenStorage($inner, 3, 10);
        $this->assertTrue($storage->blacklist('test', time() + 3600));
        $this->assertSame(1, $inner->calls);
    }

    public function testRetryOnStorageError(): void
    {
        $inner = new class implements TokenStorageInterface {
            public $calls = 0;
            public function blacklist(string $jti, int $expireTime): bool {
                $this->calls++;
                if ($this->calls < 3) {
                    throw JWTException::storageError('temp error');
                }
                return true;
            }
            public function isBlacklisted(string $jti): bool { return false; }
            public function cleanup(): bool { return true; }
        };
        $storage = new RetryTokenStorage($inner, 3, 10);
        $this->assertTrue($storage->blacklist('test', time() + 3600));
        $this->assertSame(3, $inner->calls);
    }

    public function testConfigErrorDoesNotRetry(): void
    {
        $inner = new class implements TokenStorageInterface {
            public $calls = 0;
            public function blacklist(string $jti, int $expireTime): bool {
                $this->calls++;
                throw JWTException::configError('bad config');
            }
            public function isBlacklisted(string $jti): bool { return false; }
            public function cleanup(): bool { return true; }
        };
        $storage = new RetryTokenStorage($inner, 3, 10);
        try {
            $storage->blacklist('test', time() + 3600);
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
        }
        $this->assertSame(1, $inner->calls);
    }

    public function testThrowsAfterMaxRetries(): void
    {
        $inner = new class implements TokenStorageInterface {
            public $calls = 0;
            public function blacklist(string $jti, int $expireTime): bool {
                $this->calls++;
                throw JWTException::storageError('always fail');
            }
            public function isBlacklisted(string $jti): bool { return false; }
            public function cleanup(): bool { return true; }
        };
        $storage = new RetryTokenStorage($inner, 3, 10);
        try {
            $storage->blacklist('test', time() + 3600);
            $this->fail('Expected exception not thrown');
        } catch (JWTException $e) {
            $this->assertStringContainsString('failed after 3 attempts', $e->getMessage());
        }
        $this->assertSame(3, $inner->calls);
    }

    public function testIsBlacklistedRetry(): void
    {
        $inner = new class implements TokenStorageInterface {
            public $calls = 0;
            public function blacklist(string $jti, int $expireTime): bool { return true; }
            public function isBlacklisted(string $jti): bool {
                $this->calls++;
                if ($this->calls < 2) {
                    throw new \RuntimeException('connection lost');
                }
                return true;
            }
            public function cleanup(): bool { return true; }
        };
        $storage = new RetryTokenStorage($inner, 3, 10);
        $this->assertTrue($storage->isBlacklisted('test'));
        $this->assertSame(2, $inner->calls);
    }

    public function testCleanupRetry(): void
    {
        $inner = new class implements TokenStorageInterface {
            public $calls = 0;
            public function blacklist(string $jti, int $expireTime): bool { return true; }
            public function isBlacklisted(string $jti): bool { return false; }
            public function cleanup(): bool {
                $this->calls++;
                if ($this->calls < 2) {
                    throw JWTException::storageError('temp fail');
                }
                return true;
            }
        };
        $storage = new RetryTokenStorage($inner, 3, 10);
        $this->assertTrue($storage->cleanup());
        $this->assertSame(2, $inner->calls);
    }
}
