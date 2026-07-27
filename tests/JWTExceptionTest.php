<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWTException;
use PHPUnit\Framework\TestCase;

class JWTExceptionTest extends TestCase
{
    public function testExpired(): void
    {
        $e = JWTException::expired();
        $this->assertSame('Token has expired', $e->getMessage());
        $this->assertSame(JWTException::TOKEN_EXPIRED, $e->getCode());
    }
    public function testInvalid(): void
    {
        $e = JWTException::invalid('Custom error');
        $this->assertSame('Custom error', $e->getMessage());
        $this->assertSame(JWTException::TOKEN_INVALID, $e->getCode());
    }
    public function testInvalidDefaultMessage(): void
    {
        $e = JWTException::invalid();
        $this->assertSame('Invalid token', $e->getMessage());
    }
    public function testBlacklisted(): void
    {
        $e = JWTException::blacklisted();
        $this->assertSame('Token has been blacklisted', $e->getMessage());
        $this->assertSame(JWTException::TOKEN_BLACKLISTED, $e->getCode());
    }
    public function testStorageError(): void
    {
        $e = JWTException::storageError('Disk full');
        $this->assertSame('Storage error: Disk full', $e->getMessage());
        $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
    }
    public function testConfigError(): void
    {
        $e = JWTException::configError('Missing key');
        $this->assertSame('Configuration error: Missing key', $e->getMessage());
        $this->assertSame(JWTException::CONFIG_ERROR, $e->getCode());
    }
    public function testNetworkError(): void
    {
        $e = JWTException::networkError('Timeout');
        $this->assertSame('Network error: Timeout', $e->getMessage());
        $this->assertSame(JWTException::NETWORK_ERROR, $e->getCode());
    }
    public function testFromExceptionNetwork(): void
    {
        $original = new \Exception('connection refused');
        $e = JWTException::fromException($original, 'Redis');
        $this->assertSame(JWTException::NETWORK_ERROR, $e->getCode());
        $this->assertStringContainsString('Redis', $e->getMessage());
    }
    public function testFromExceptionTimeout(): void
    {
        $original = new \Exception('read timeout');
        $e = JWTException::fromException($original);
        $this->assertSame(JWTException::NETWORK_ERROR, $e->getCode());
    }
    public function testFromExceptionStorage(): void
    {
        $original = new \Exception('unknown error');
        $e = JWTException::fromException($original);
        $this->assertSame(JWTException::STORAGE_ERROR, $e->getCode());
    }
    public function testExceptionInheritance(): void
    {
        $e = JWTException::expired();
        $this->assertInstanceOf(\Exception::class, $e);
    }
}
