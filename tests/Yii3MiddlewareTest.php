<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\FileTokenStorage;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\Yii3\Middleware;
use PHPUnit\Framework\TestCase;

class Yii3MiddlewareTest extends TestCase
{
    private $tempDir;
    private $jwt;
    private $factory;
    private $handler;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->tempDir = sys_get_temp_dir() . '/jwt_yii3_mw_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);

        $this->jwt = new JWT([
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            '_token_storage' => new FileTokenStorage($this->tempDir),
        ]);

        $this->factory = new \JwtTestYii3ResponseFactory();
        $this->handler = new \JwtTestPsrHandler(new \JwtTestYii3Response());
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            foreach (array_diff(scandir($this->tempDir), ['.', '..']) as $f) {
                unlink("{$this->tempDir}/{$f}");
            }
            rmdir($this->tempDir);
        }
    }

    private function middleware(array $except = []): Middleware
    {
        return new Middleware($this->jwt, $this->factory, $except);
    }

    public function testExceptedPathBypassesAuth(): void
    {
        $request  = new \JwtTestPsrRequest('api/login');
        $response = $this->middleware(['api/login'])->process($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $this->handler->lastRequest);
    }

    public function testExceptedPathMatchesWithLeadingSlash(): void
    {
        $request = new \JwtTestPsrRequest('/api/health');

        $this->assertSame(200, $this->middleware(['api/health'])->process($request, $this->handler)->getStatusCode());
    }

    public function testProtectedPathStillRequiresToken(): void
    {
        $request = new \JwtTestPsrRequest('/api/profile');

        $this->assertSame(401, $this->middleware(['api/login'])->process($request, $this->handler)->getStatusCode());
    }

    public function testMissingTokenReturns401(): void
    {
        $response = $this->middleware()->process(new \JwtTestPsrRequest('api/profile'), $this->handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['code' => 401, 'msg' => 'Token not provided', 'data' => null], $response->getData());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testInvalidTokenReturns401(): void
    {
        $request  = new \JwtTestPsrRequest('api/profile', ['Authorization' => 'Bearer bad.token.here']);
        $response = $this->middleware()->process($request, $this->handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertNotEmpty($response->getData()['msg']);
    }

    public function testExpiredTokenReturns401(): void
    {
        $token   = $this->jwt->encode(['uid' => 1], -50);
        $request = new \JwtTestPsrRequest('api/profile', ['Authorization' => 'Bearer ' . $token]);

        $response = $this->middleware()->process($request, $this->handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token has expired', $response->getData()['msg']);
    }

    public function testRefreshTokenIsRejectedAsAccessToken(): void
    {
        $token   = $this->jwt->encode(['uid' => 1, 'token_type' => 'refresh']);
        $request = new \JwtTestPsrRequest('api/profile', ['Authorization' => 'Bearer ' . $token]);

        $this->assertSame(401, $this->middleware()->process($request, $this->handler)->getStatusCode());
    }

    public function testBlacklistedTokenReturns401(): void
    {
        $token = $this->jwt->encode(['uid' => 1]);
        $this->jwt->blacklist($token);
        $request = new \JwtTestPsrRequest('api/profile', ['Authorization' => 'Bearer ' . $token]);

        $this->assertSame(401, $this->middleware()->process($request, $this->handler)->getStatusCode());
    }

    public function testValidTokenPassesThroughWithPayloadAttribute(): void
    {
        $token   = $this->jwt->encode(['uid' => 9, 'role' => 'admin']);
        $request = new \JwtTestPsrRequest('api/profile', ['Authorization' => 'Bearer ' . $token]);

        $response = $this->middleware()->process($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(9, $this->handler->lastRequest->getAttribute('jwt_payload')['uid']);
        $this->assertSame('admin', $this->handler->lastRequest->getAttribute('jwt_payload')['role']);
    }

    public function testOriginalRequestIsNotMutated(): void
    {
        $token   = $this->jwt->encode(['uid' => 9]);
        $request = new \JwtTestPsrRequest('api/profile', ['Authorization' => 'Bearer ' . $token]);

        $this->middleware()->process($request, $this->handler);

        // PSR-15：中间件只能派生新请求，不能改写入参
        $this->assertNull($request->getAttribute('jwt_payload'));
    }

    public function testInvalidExceptPatternDoesNotBreakTheRequest(): void
    {
        $token   = $this->jwt->encode(['uid' => 9]);
        $request = new \JwtTestPsrRequest('api/profile', ['Authorization' => 'Bearer ' . $token]);

        $response = $this->middleware(['api/(('])->process($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }
}
