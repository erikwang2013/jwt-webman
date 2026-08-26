<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\JWTFactory;
use PHPUnit\Framework\TestCase;

class JWTFactoryTest extends TestCase
{
    private $baseConfig;

    protected function setUp(): void
    {
        $this->baseConfig = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'algorithm'      => 'HS256',
            'issuer'         => 'test',
            'audience'       => 'test',
            'leeway'         => 0,
            'default_expire' => 3600,
            'refresh_expire' => 7200,
            'storage'        => ['type' => 'file'],
            'advanced'       => [
                'retry_attempts'   => 1,
                'retry_delay'      => 100,
                'auto_cleanup'     => false,
                'cleanup_interval' => 3600,
            ],
        ];
    }

    public function testCreateFromConfig(): void
    {
        $jwt = JWTFactory::createFromConfig($this->baseConfig);
        $this->assertInstanceOf(JWT::class, $jwt);
        $this->assertSame('HS256', $jwt->getAlgorithm());
    }

    public function testSecretKeyTooShortThrows(): void
    {
        $config = $this->baseConfig;
        $config['secret_key'] = 'short-key';
        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::CONFIG_ERROR);
        JWTFactory::createFromConfig($config);
    }

    public function testEmptySecretKeyThrows(): void
    {
        $config = $this->baseConfig;
        $config['secret_key'] = '';
        $this->expectException(JWTException::class);
        JWTFactory::createFromConfig($config);
    }

    public function testEncodeDecodeWithFactoryCreatedInstance(): void
    {
        $jwt = JWTFactory::createFromConfig($this->baseConfig);
        $token = $jwt->encode(['uid' => 999]);
        $payload = $jwt->decode($token);
        $this->assertSame(999, $payload['uid']);
    }

    public function testRedisStorageRequiresResolver(): void
    {
        $config = $this->baseConfig;
        $config['storage']['type'] = 'redis';
        $this->expectException(JWTException::class);
        JWTFactory::createFromConfig($config);
    }

    public function testDatabaseStorageRequiresPdo(): void
    {
        $config = $this->baseConfig;
        $config['storage']['type'] = 'database';
        $this->expectException(JWTException::class);
        JWTFactory::createFromConfig($config);
    }

    public function testMemcachedStorageCreatesDefault(): void
    {
        if (!class_exists('Memcached')) {
            $this->markTestSkipped('Memcached extension not available');
        }
        $config = $this->baseConfig;
        $config['storage']['type'] = 'memcached';
        $config['storage']['servers'] = [['127.0.0.1', 11211]];
        // Should not throw when creating with default Memcached
        $jwt = JWTFactory::createFromConfig($config);
        $this->assertInstanceOf(JWT::class, $jwt);
    }

    public function testMemcachedStorageWithoutExtensionThrows(): void
    {
        if (class_exists('Memcached')) {
            $this->markTestSkipped('Memcached extension available');
        }
        $config = $this->baseConfig;
        $config['storage']['type'] = 'memcached';
        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::STORAGE_ERROR);
        JWTFactory::createFromConfig($config);
    }

    public function testRedisStorageWithResolverWorks(): void
    {
        $config = $this->baseConfig;
        $config['storage']['type'] = 'redis';
        $redis = new class {
            public $data = [];
            public function ping() { return 'PONG'; }
            public function setex($k, $t, $v) { $this->data[$k] = $v; return true; }
            public function exists($k) { return isset($this->data[$k]) ? 1 : 0; }
        };
        $jwt = JWTFactory::createFromConfig($config, null, ['redis' => fn () => $redis]);
        $token = $jwt->encode(['uid' => 8]);
        $this->assertSame(8, $jwt->decode($token)['uid']);
        $jwt->blacklist($token);
        $this->assertTrue($jwt->isBlacklisted($token));
    }

    public function testRedisStorageUsesPrefixFromConfig(): void
    {
        $config = $this->baseConfig;
        $config['storage']['type'] = 'redis';
        $config['storage']['prefix'] = 'mybl:';
        $redis = new class {
            public $keys = [];
            public function ping() { return 'PONG'; }
            public function setex($k, $t, $v) { $this->keys[] = $k; return true; }
            public function exists($k) { return 0; }
        };
        $jwt = JWTFactory::createFromConfig($config, null, ['redis' => fn () => $redis]);
        $token = $jwt->encode(['uid' => 8]);
        $jwt->blacklist($token);
        $this->assertStringStartsWith('mybl:', $redis->keys[0]);
    }

    public function testDatabaseStorageWithPdoWorks(): void
    {
        $config = $this->baseConfig;
        $config['storage']['type'] = 'database';
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $jwt = JWTFactory::createFromConfig($config, null, ['pdo' => $pdo]);
        $token = $jwt->encode(['uid' => 8]);
        $this->assertSame(8, $jwt->decode($token)['uid']);
        $jwt->blacklist($token);
        $this->assertTrue($jwt->isBlacklisted($token));
    }

    public function testDatabaseStorageWithCustomTableName(): void
    {
        $config = $this->baseConfig;
        $config['storage']['type'] = 'database';
        $config['storage']['config']['table_name'] = 'custom_bl';
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $jwt = JWTFactory::createFromConfig($config, null, ['pdo' => $pdo]);
        $token = $jwt->encode(['uid' => 8]);
        $jwt->blacklist($token);
        $this->assertTrue($jwt->isBlacklisted($token));
        $stmt = $pdo->query("SELECT COUNT(*) FROM custom_bl");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testRetryWrapperIsAppliedWhenRetryAttemptsGreaterThanOne(): void
    {
        $config = $this->baseConfig;
        $config['advanced']['retry_attempts'] = 3;
        $jwt = JWTFactory::createFromConfig($config);
        $property = new \ReflectionProperty(JWT::class, 'tokenStorage');
        $property->setAccessible(true);
        $this->assertInstanceOf(\Erikwang2013\Jwt\RetryTokenStorage::class, $property->getValue($jwt));
        $token = $jwt->encode(['uid' => 8]);
        $this->assertSame(8, $jwt->decode($token)['uid']);
    }

    public function testFileStorageWithCustomPath(): void
    {
        $dir = sys_get_temp_dir() . '/jwt_factory_' . bin2hex(random_bytes(6));
        mkdir($dir, 0755, true);
        try {
            $config = $this->baseConfig;
            $config['storage']['path'] = $dir;
            $jwt = JWTFactory::createFromConfig($config);
            $token = $jwt->encode(['uid' => 8]);
            $jwt->blacklist($token);
            $this->assertTrue($jwt->isBlacklisted($token));
            $this->assertNotEmpty(glob($dir . '/*.json'));
        } finally {
            foreach (glob($dir . '/*.json') ?: [] as $f) {
                unlink($f);
            }
            rmdir($dir);
        }
    }

    public function testAutoCleanupEnabledDoesNotBreakCreation(): void
    {
        $config = $this->baseConfig;
        $config['advanced']['auto_cleanup'] = true;
        $jwt = JWTFactory::createFromConfig($config);
        $this->assertInstanceOf(JWT::class, $jwt);
        $token = $jwt->encode(['uid' => 8]);
        $this->assertSame(8, $jwt->decode($token)['uid']);
    }
}
