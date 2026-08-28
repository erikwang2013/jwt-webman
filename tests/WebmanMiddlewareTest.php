<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Webman\Middleware;
use Webman\Http\Request;
use Webman\Http\Response;
use PHPUnit\Framework\TestCase;

class WebmanMiddlewareTest extends TestCase
{
    private $jwtConfig;
    private $tempDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->tempDir = sys_get_temp_dir() . '/jwt_webman_mw_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
        $this->jwtConfig = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            'storage'        => ['type' => 'file', 'path' => $this->tempDir],
            'middleware'     => ['except' => []],
        ];
        $GLOBALS['__jwt_fw']['config'] = [
            'plugin' => [
                'erikwang2013' => [
                    'jwt' => ['jwt' => $this->jwtConfig],
                ],
            ],
        ];
        // 重置 Middleware 静态缓存，保证每个测试用独立配置
        $property = new \ReflectionProperty(Middleware::class, 'jwtInstance');
        $property->setAccessible(true);
        $property->setValue(null, null);
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

    private function next(): callable
    {
        return function () {
            return new Response(200, [], 'next-result');
        };
    }

    public function testExceptPathBypassesAuth(): void
    {
        $this->jwtConfig['middleware']['except'] = ['login'];
        $GLOBALS['__jwt_fw']['config']['plugin']['erikwang2013']['jwt']['jwt'] = $this->jwtConfig;
        $request = new Request('login');
        $result = (new Middleware())->process($request, $this->next());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('next-result', $result->rawBody());
    }

    public function testNoTokenReturns401(): void
    {
        $request = new Request('private');
        $response = (new Middleware())->process($request, $this->next());
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        $this->assertSame('Token not provided', $body['msg']);
    }

    public function testValidTokenPassesThroughAndSetsPayload(): void
    {
        $jwt = $this->resolveJwt();
        $token = $jwt->encode(['uid' => 11]);
        $request = new Request('private', ['Authorization' => 'Bearer ' . $token]);

        $result = (new Middleware())->process($request, $this->next());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('next-result', $result->rawBody());
        $this->assertSame(11, $request->jwt_payload['uid']);
    }

    public function testInvalidTokenReturns401(): void
    {
        $response = (new Middleware())->process(
            new Request('private', ['Authorization' => 'Bearer invalid.token.here']),
            $this->next()
        );
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        $this->assertSame(401, $body['code']);
        $this->assertNotEmpty($body['msg']);
    }

    public function testBlacklistedTokenReturns401(): void
    {
        $jwt = $this->resolveJwt();
        $token = $jwt->encode(['uid' => 11]);
        $jwt->blacklist($token);

        $response = (new Middleware())->process(
            new Request('private', ['Authorization' => 'Bearer ' . $token]),
            $this->next()
        );
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        $this->assertSame('Token has been blacklisted', $body['msg']);
    }

    private function resolveJwt(): \Erikwang2013\Jwt\JWT
    {
        $method = new \ReflectionMethod(Middleware::class, 'getJWT');
        $method->setAccessible(true);
        return $method->invoke(null, $GLOBALS['__jwt_fw']['config']['plugin']['erikwang2013']['jwt']['jwt']);
    }
}
