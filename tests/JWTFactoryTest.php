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
}
