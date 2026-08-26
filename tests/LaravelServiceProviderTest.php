<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\Laravel\JWTServiceProvider;
use Erikwang2013\Jwt\Laravel\Middleware;
use PHPUnit\Framework\TestCase;

class LaravelServiceProviderTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->app = new \JwtTestApp();
        $GLOBALS['__jwt_fw']['app'] = $this->app;
        $GLOBALS['__jwt_fw']['config']['jwt']['secret_key'] = 'this-is-a-very-secure-secret-key-for-testing-256bits';
    }

    public function testRegisterMergesConfigAndBindsSingleton(): void
    {
        $provider = new JWTServiceProvider($this->app);
        $provider->register();

        $config = config('jwt');
        $this->assertSame('HS256', $config['algorithm']);
        $this->assertSame(
            'this-is-a-very-secure-secret-key-for-testing-256bits',
            $config['secret_key']
        );

        $jwt = app('erik.jwt');
        $this->assertInstanceOf(JWT::class, $jwt);
        $token = $jwt->encode(['uid' => 1]);
        $this->assertSame(1, $jwt->decode($token)['uid']);
    }

    public function testRegisterWithRedisStorage(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['storage']['type'] = 'redis';
        $redis = new \JwtTestFakeRedis();
        $GLOBALS['__jwt_fw']['redis'] = $redis;

        $provider = new JWTServiceProvider($this->app);
        $provider->register();
        $jwt = app('erik.jwt');
        $this->assertInstanceOf(JWT::class, $jwt);

        $token = $jwt->encode(['uid' => 5]);
        $this->assertSame(5, $jwt->decode($token)['uid']);
        $this->assertTrue($jwt->blacklist($token));
        $this->assertTrue($jwt->isBlacklisted($token));
    }

    public function testBootRegistersMiddlewareAliasInHttpMode(): void
    {
        $GLOBALS['__jwt_fw']['console_running'] = false;
        $provider = new JWTServiceProvider($this->app);
        $provider->boot();

        $this->assertSame(
            Middleware::class,
            $GLOBALS['__jwt_fw']['middleware_aliases']['jwt']
        );
        $this->assertSame([], $GLOBALS['__jwt_fw']['published']);
        $this->assertSame([], $GLOBALS['__jwt_fw']['commands']);
    }

    public function testBootInConsoleModePublishesAndRegistersCommand(): void
    {
        $GLOBALS['__jwt_fw']['console_running'] = true;
        $provider = new JWTServiceProvider($this->app);
        $provider->boot();

        $this->assertArrayHasKey('jwt-config', $GLOBALS['__jwt_fw']['published']);
        $this->assertSame(
            \Erikwang2013\Jwt\Laravel\InstallCommand::class,
            $GLOBALS['__jwt_fw']['commands'][0]
        );
        $this->assertSame(
            Middleware::class,
            $GLOBALS['__jwt_fw']['middleware_aliases']['jwt']
        );
    }
}
