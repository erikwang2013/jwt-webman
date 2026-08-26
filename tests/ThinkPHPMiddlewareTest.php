<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\FileTokenStorage;
use Erikwang2013\Jwt\ThinkPHP\Middleware;
use think\Request;
use think\Response;
use PHPUnit\Framework\TestCase;

class ThinkPHPMiddlewareTest extends TestCase
{
    private $jwt;
    private $tempDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->tempDir = sys_get_temp_dir() . '/jwt_think_mw_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
        $this->jwt = new JWT([
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            '_token_storage' => new FileTokenStorage($this->tempDir),
        ]);
        $GLOBALS['__jwt_fw']['bindings']['erik.jwt'] = $this->jwt;
        $GLOBALS['__jwt_fw']['config']['jwt'] = ['middleware' => ['except' => []]];
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
            return (new Response())->data('next-result');
        };
    }

    public function testExceptPathBypassesAuth(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['middleware']['except'] = ['public'];
        $request = new Request('public');
        $result = (new Middleware())->handle($request, $this->next());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('next-result', $result->getData());
    }

    public function testNoTokenReturns401(): void
    {
        $request = new Request('private');
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getCode());
        $this->assertSame('Token not provided', $response->getData()['msg']);
    }

    public function testValidTokenPassesThroughAndSetsPayload(): void
    {
        $token = $this->jwt->encode(['uid' => 9]);
        $request = new Request('private', ['Authorization' => 'Bearer ' . $token]);
        $result = (new Middleware())->handle($request, $this->next());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('next-result', $result->getData());
        $this->assertSame(9, $request->jwt_payload['uid']);
    }

    public function testInvalidTokenReturns401(): void
    {
        $request = new Request('private', ['Authorization' => 'Bearer bad.token.here']);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame(401, $response->getCode());
        $this->assertSame(401, $response->getData()['code']);
        $this->assertNotEmpty($response->getData()['msg']);
    }

    public function testExpiredTokenReturns401(): void
    {
        $token = $this->jwt->encode(['uid' => 9], -50);
        $request = new Request('private', ['Authorization' => 'Bearer ' . $token]);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame(401, $response->getCode());
        $this->assertSame('Token has expired', $response->getData()['msg']);
    }
}
