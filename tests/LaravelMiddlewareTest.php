<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\FileTokenStorage;
use Erikwang2013\Jwt\Laravel\Middleware;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class LaravelMiddlewareTest extends TestCase
{
    private $jwt;
    private $tempDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->tempDir = sys_get_temp_dir() . '/jwt_laravel_mw_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
        $this->jwt = new JWT([
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            '_token_storage' => new FileTokenStorage($this->tempDir),
        ]);
        $GLOBALS['__jwt_fw']['bindings']['erik.jwt'] = $this->jwt;
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
            return 'next-result';
        };
    }

    public function testExceptPathBypassesAuth(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['middleware']['except'] = ['login'];
        $request = new Request('login', []);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame('next-result', $response);
    }

    public function testInvalidExceptPatternIsIgnored(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['middleware']['except'] = ['['];
        $request = new Request('private', []);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertInstanceOf(\JwtTestLaravelJsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testNoTokenReturns401(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['middleware']['except'] = [];
        $request = new Request('private', []);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token not provided', $response->getData()['msg']);
    }

    public function testValidTokenPassesThroughAndSetsPayload(): void
    {
        $token = $this->jwt->encode(['uid' => 7]);
        $request = new Request('private', ['Authorization' => 'Bearer ' . $token]);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame('next-result', $response);
        $this->assertSame(7, $request->attributes->get('jwt_payload')['uid']);
    }

    public function testInvalidTokenReturns401WithMessage(): void
    {
        $request = new Request('private', ['Authorization' => 'Bearer invalid.token.here']);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(401, $response->getData()['code']);
        $this->assertNotEmpty($response->getData()['msg']);
    }

    public function testBlacklistedTokenReturns401(): void
    {
        $token = $this->jwt->encode(['uid' => 7]);
        $this->jwt->blacklist($token);
        $request = new Request('private', ['Authorization' => 'Bearer ' . $token]);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token has been blacklisted', $response->getData()['msg']);
    }

    public function testExpiredTokenReturns401(): void
    {
        $token = $this->jwt->encode(['uid' => 7], -100);
        $request = new Request('private', ['Authorization' => 'Bearer ' . $token]);
        $response = (new Middleware())->handle($request, $this->next());
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token has expired', $response->getData()['msg']);
    }
}
