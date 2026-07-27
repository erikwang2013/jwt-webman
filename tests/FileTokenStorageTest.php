<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\FileTokenStorage;
use PHPUnit\Framework\TestCase;

class FileTokenStorageTest extends TestCase
{
    private $tempDir;
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/jwt_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, true);
    }
    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) return;
        $files = array_diff(scandir($this->tempDir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$this->tempDir}/{$file}";
            is_dir($path) ? $this->rmdirRec($path) : unlink($path);
        }
        rmdir($this->tempDir);
    }
    private function rmdirRec(string $dir): void
    {
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) ? $this->rmdirRec($p) : unlink($p);
        }
        rmdir($dir);
    }
    public function testBlacklistAndCheck(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $jti = bin2hex(random_bytes(16));
        $this->assertTrue($s->blacklist($jti, time() + 3600));
        $this->assertTrue($s->isBlacklisted($jti));
    }
    public function testNotBlacklisted(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $this->assertFalse($s->isBlacklisted('abcdef0123456789abcdef0123456789'));
    }
    public function testExpiredTokenNotBlacklisted(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $jti = bin2hex(random_bytes(16));
        $s->blacklist($jti, time() - 3600);
        $this->assertFalse($s->isBlacklisted($jti));
    }
    public function testAlreadyExpiredTokenReturnsTrue(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $this->assertTrue($s->blacklist(bin2hex(random_bytes(16)), time() - 100));
    }
    public function testCleanup(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $jti = bin2hex(random_bytes(16));
        $s->blacklist($jti, time() - 3600);
        $this->assertTrue($s->cleanup());
        $this->assertFalse($s->isBlacklisted($jti));
    }
    public function testInvalidJtiThrows(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $this->expectException(\Erikwang2013\Jwt\JWTException::class);
        $s->blacklist('not-hex!@#', time() + 3600);
    }
    public function testGetStats(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $stats = $s->getStats();
        $this->assertArrayHasKey('total_files', $stats);
        $this->assertArrayHasKey('valid_tokens', $stats);
        $this->assertArrayHasKey('expired_tokens', $stats);
        $this->assertSame($this->tempDir, $stats['storage_path']);
    }
    public function testSetGcProbability(): void
    {
        $s = new FileTokenStorage($this->tempDir);
        $s->setGcProbability(0.5);
        $s->setGcProbability(-1.0);
        $s->setGcProbability(2.0);
        $this->assertTrue(true);
    }
}
