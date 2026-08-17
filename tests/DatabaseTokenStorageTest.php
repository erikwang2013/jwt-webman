<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\DatabaseTokenStorage;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseTokenStorageTest extends TestCase
{
    private $pdo;
    private $storage;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->storage = new DatabaseTokenStorage($this->pdo);
    }

    public function testBlacklistThenIsBlacklisted(): void
    {
        $jti = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6';
        $this->assertTrue($this->storage->blacklist($jti, time() + 3600));
        $this->assertTrue($this->storage->isBlacklisted($jti));
    }

    public function testNotBlacklistedJti(): void
    {
        $this->assertFalse($this->storage->isBlacklisted('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6'));
    }

    public function testDuplicateBlacklistFallsBackToUpdate(): void
    {
        $jti = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6';
        $this->assertTrue($this->storage->blacklist($jti, time() + 3600));
        $this->assertTrue($this->storage->blacklist($jti, time() + 7200));
        $this->assertTrue($this->storage->isBlacklisted($jti));

        $stmt = $this->pdo->query("SELECT expire_time FROM jwt_blacklist WHERE jti = '{$jti}'");
        $expire = (int) $stmt->fetchColumn();
        $this->assertGreaterThan(time() + 7190, $expire);
    }

    public function testCleanupRemovesExpiredEntries(): void
    {
        $jti = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6';
        $this->assertTrue($this->storage->blacklist($jti, time() - 100));
        $this->assertFalse($this->storage->isBlacklisted($jti));

        $this->assertTrue($this->storage->cleanup());

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM jwt_blacklist WHERE jti = '{$jti}'");
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}
