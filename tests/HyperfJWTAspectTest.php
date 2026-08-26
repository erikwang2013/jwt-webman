<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Hyperf\JWTAspect;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\FileTokenStorage;
use Hyperf\Contract\ConfigInterface;
use PHPUnit\Framework\TestCase;

class HyperfJWTAspectTest extends TestCase
{
    private $jwt;
    private $tempDir;
    private $container;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->tempDir = sys_get_temp_dir() . '/jwt_aspect_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
        $this->jwt = new JWT([
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            '_token_storage' => new FileTokenStorage($this->tempDir),
        ]);
        $GLOBALS['__jwt_fw']['config']['jwt'] = ['middleware' => ['except' => []]];
        $this->container = new \JwtTestContainer([
            JWT::class => $this->jwt,
            ConfigInterface::class => new \JwtTestHyperfConfig(),
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = array_diff(scandir($this->tempDir), ['.', '..']);
            foreach ($files as $f) {
                unlink("{$this->tempDir}/{$f}");
            }
            rmdir($this->tempDir);
        }
    }

    private function makeAspect(string $path = '/', array $headers = []): JWTAspect
    {
        return new JWTAspect(
            $this->container,
            new \JwtTestHyperfRequest($path, $headers),
            new \JwtTestHyperfResponse()
        );
    }

    public function testAnnotationsTargetHyperfJwt(): void
    {
        $reflection = new \ReflectionClass(JWTAspect::class);
        $this->assertSame(
            [\Erikwang2013\Jwt\Hyperf\JWT::class],
            $reflection->getProperty('annotations')->getValue(new JWTAspect(
                $this->container,
                new \JwtTestHyperfRequest(),
                new \JwtTestHyperfResponse()
            ))
        );
    }

    public function testExceptPathBypassesAuth(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['middleware']['except'] = ['/login'];
        $pjp = new \Hyperf\Di\Aop\ProceedingJoinPoint('pjp-result');
        $result = $this->makeAspect('/login')->process($pjp);
        $this->assertSame('pjp-result', $result);
    }

    public function testNoTokenReturns401(): void
    {
        $pjp = new \Hyperf\Di\Aop\ProceedingJoinPoint('pjp-result');
        $response = $this->makeAspect('/private')->process($pjp);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token not provided', $response->getData()['msg']);
    }

    public function testValidTokenProceedsAndSetsContext(): void
    {
        $token = $this->jwt->encode(['uid' => 33]);
        $pjp = new \Hyperf\Di\Aop\ProceedingJoinPoint('pjp-result');
        $result = $this->makeAspect('/private', ['Authorization' => 'Bearer ' . $token])->process($pjp);
        $this->assertSame('pjp-result', $result);
        $this->assertSame(33, \Hyperf\Context\Context::get('jwt_payload')['uid']);
    }

    public function testInvalidTokenReturns401(): void
    {
        $pjp = new \Hyperf\Di\Aop\ProceedingJoinPoint('pjp-result');
        $response = $this->makeAspect('/private', ['Authorization' => 'Bearer bad.token.here'])->process($pjp);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(401, $response->getData()['code']);
    }
}
