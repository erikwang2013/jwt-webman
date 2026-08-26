<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\ThinkPHP\Facade;
use Erikwang2013\Jwt\ThinkPHP\JWTService;
use Erikwang2013\Jwt\ThinkPHP\Middleware;
use PHPUnit\Framework\TestCase;

class ThinkPHPIntegrationTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->app = new \JwtTestApp();
        $GLOBALS['__jwt_fw']['app'] = $this->app;
        $GLOBALS['__jwt_fw']['config']['jwt'] = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            'storage'        => ['type' => 'file'],
        ];
    }

    public function testFacadeClass(): void
    {
        $method = new \ReflectionMethod(Facade::class, 'getFacadeClass');
        $this->assertSame('erik.jwt', $method->invoke(null));
        $this->assertTrue(is_subclass_of(Facade::class, \think\Facade::class));
    }

    public function testRegisterBindsService(): void
    {
        $service = new JWTService($this->app);
        $service->register();

        $jwt = app('erik.jwt');
        $this->assertInstanceOf(JWT::class, $jwt);
        $token = $jwt->encode(['uid' => 3]);
        $this->assertSame(3, $jwt->decode($token)['uid']);
    }

    public function testRegisterWithRedisStorage(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['storage']['type'] = 'redis';
        $redis = new \JwtTestFakeRedis();
        $GLOBALS['__jwt_fw']['redis'] = $redis;

        $service = new JWTService($this->app);
        $service->register();
        $jwt = app('erik.jwt');

        $token = $jwt->encode(['uid' => 3]);
        $this->assertSame(3, $jwt->decode($token)['uid']);
        $jwt->blacklist($token);
        $this->assertTrue($jwt->isBlacklisted($token));
    }

    public function testRegisterWithDatabaseStorage(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['storage']['type'] = 'database';
        $GLOBALS['__jwt_fw']['pdo'] = new \PDO('sqlite::memory:');

        $service = new JWTService($this->app);
        $service->register();
        $jwt = app('erik.jwt');

        $token = $jwt->encode(['uid' => 3]);
        $this->assertSame(3, $jwt->decode($token)['uid']);
        $jwt->blacklist($token);
        $this->assertTrue($jwt->isBlacklisted($token));
    }

    public function testBootAliasesMiddleware(): void
    {
        $service = new JWTService($this->app);
        $service->boot();
        $this->assertSame(
            Middleware::class,
            $GLOBALS['__jwt_fw']['middleware_aliases']['jwt']
        );
    }
}
