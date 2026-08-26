<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Hyperf\ConfigProvider;
use Erikwang2013\Jwt\Hyperf\InstallCommand;
use Erikwang2013\Jwt\Hyperf\JWT as HyperfJWT;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;
use Hyperf\Contract\ConfigInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

class HyperfConfigProviderTest extends TestCase
{
    protected function setUp(): void
    {
        jwt_fw_reset();
        $GLOBALS['__jwt_fw']['config']['jwt'] = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            'storage'        => ['type' => 'file'],
        ];
    }

    public function testInvokeReturnsExpectedStructure(): void
    {
        $definitions = (new ConfigProvider())->__invoke();
        $this->assertArrayHasKey('dependencies', $definitions);
        $this->assertArrayHasKey(JWT::class, $definitions['dependencies']);
        $this->assertIsCallable($definitions['dependencies'][JWT::class]);
        $this->assertContains(InstallCommand::class, $definitions['commands']);
        $this->assertSame('config', $definitions['publish'][0]['id']);
        $this->assertSame('JWT config file.', $definitions['publish'][0]['description']);
        $this->assertSame(BASE_PATH . '/config/autoload/jwt.php', $definitions['publish'][0]['destination']);
    }

    public function testDependencyClosureBuildsJwt(): void
    {
        $definitions = (new ConfigProvider())->__invoke();
        $container = new \JwtTestContainer([
            ConfigInterface::class => new \JwtTestHyperfConfig(),
            LoggerInterface::class => new NullLogger(),
        ]);
        $jwt = $definitions['dependencies'][JWT::class]($container);
        $this->assertInstanceOf(JWT::class, $jwt);
        $token = $jwt->encode(['uid' => 2]);
        $this->assertSame(2, $jwt->decode($token)['uid']);
    }

    public function testDependencyClosureWithRedisStorage(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['storage']['type'] = 'redis';
        $definitions = (new ConfigProvider())->__invoke();
        $container = new \JwtTestContainer([
            ConfigInterface::class => new \JwtTestHyperfConfig(),
            LoggerInterface::class => new NullLogger(),
            \Hyperf\Redis\Redis::class => new \Hyperf\Redis\Redis(),
        ]);
        $jwt = $definitions['dependencies'][JWT::class]($container);
        $token = $jwt->encode(['uid' => 2]);
        $this->assertSame(2, $jwt->decode($token)['uid']);
    }

    public function testHyperfJwtIsAnAttributeTargetingMethods(): void
    {
        $this->assertTrue(is_subclass_of(HyperfJWT::class, \Hyperf\Di\Annotation\AbstractAnnotation::class));
        $reflection = new \ReflectionClass(HyperfJWT::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $this->assertCount(1, $attributes);
        $this->assertSame(\Attribute::TARGET_METHOD, $attributes[0]->newInstance()->flags);
    }

    public function testFactoryUsedByProvider(): void
    {
        // 确保 JWTFactory 与 ConfigProvider 配合（storage 类型分发）
        $jwt = JWTFactory::createFromConfig($GLOBALS['__jwt_fw']['config']['jwt']);
        $this->assertInstanceOf(JWT::class, $jwt);
    }
}
