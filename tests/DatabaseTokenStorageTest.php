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

    public function testInvalidTableNameThrows(): void
    {
        $this->expectException(\Erikwang2013\Jwt\JWTException::class);
        $this->expectExceptionCode(\Erikwang2013\Jwt\JWTException::CONFIG_ERROR);
        new DatabaseTokenStorage($this->pdo, 'invalid-table name');
    }

    public function testCustomTableName(): void
    {
        $storage = new DatabaseTokenStorage($this->pdo, 'my_jwt_bl');
        $jti = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6';
        $this->assertTrue($storage->blacklist($jti, time() + 3600));
        $this->assertTrue($storage->isBlacklisted($jti));
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM my_jwt_bl WHERE jti = '{$jti}'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testCleanupOnEmptyTableReturnsTrue(): void
    {
        $this->assertTrue($this->storage->cleanup());
    }

    public function testExpiredEntryNotBlacklisted(): void
    {
        $jti = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6';
        $this->assertTrue($this->storage->blacklist($jti, time() - 200));
        $this->assertFalse($this->storage->isBlacklisted($jti));
    }

    public function testTableCreationFailureThrowsStorageError(): void
    {
        $pdo = new class('sqlite::memory:') extends PDO {
            public function exec(string $statement): int|false
            {
                throw new \PDOException('cannot create table');
            }
        };
        $this->expectException(\Erikwang2013\Jwt\JWTException::class);
        $this->expectExceptionCode(\Erikwang2013\Jwt\JWTException::STORAGE_ERROR);
        new DatabaseTokenStorage($pdo);
    }

    public function testOperationFailureThrowsStorageError(): void
    {
        $pdo = new class('sqlite::memory:') extends PDO {
            public function exec(string $statement): int|false
            {
                return 0;
            }

            public function prepare(string $statement, array $options = []): \PDOStatement|false
            {
                throw new \PDOException('database locked');
            }
        };
        $storage = new DatabaseTokenStorage($pdo);
        try {
            $storage->blacklist('a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6', time() + 3600);
            $this->fail('Expected exception not thrown');
        } catch (\Erikwang2013\Jwt\JWTException $e) {
            $this->assertSame(\Erikwang2013\Jwt\JWTException::STORAGE_ERROR, $e->getCode());
            $this->assertStringContainsString('database locked', $e->getMessage());
        }
    }
}
