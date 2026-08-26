<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Hyperf\Middleware;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\FileTokenStorage;
use PHPUnit\Framework\TestCase;

class HyperfMiddlewareTest extends TestCase
{
    private $jwt;
    private $tempDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->tempDir = sys_get_temp_dir() . '/jwt_hyperf_mw_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
        $this->jwt = new JWT([
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            '_token_storage' => new FileTokenStorage($this->tempDir),
        ]);
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

    private function makeMiddleware(): Middleware
    {
        return new Middleware(
            $this->jwt,
            new \JwtTestHyperfConfig(),
            new \JwtTestHyperfResponse()
        );
    }

    private function makeHandler(): \JwtTestPsrHandler
    {
        return new \JwtTestPsrHandler(new \JwtTestPsrResponse(['ok' => true]));
    }

    public function testExceptPathBypassesAuth(): void
    {
        $GLOBALS['__jwt_fw']['config']['jwt']['middleware']['except'] = ['/public'];
        $handler = $this->makeHandler();
        $response = $this->makeMiddleware()->process(
            new \JwtTestPsrRequest('/public', []),
            $handler
        );
        $this->assertSame(['ok' => true], $response->getData());
    }

    public function testNoTokenReturns401(): void
    {
        $response = $this->makeMiddleware()->process(
            new \JwtTestPsrRequest('/private', []),
            $this->makeHandler()
        );
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token not provided', $response->getData()['msg']);
    }

    public function testValidTokenPassesAttributeToHandler(): void
    {
        $token = $this->jwt->encode(['uid' => 21]);
        $handler = $this->makeHandler();
        $request = new \JwtTestPsrRequest('/private', ['Authorization' => 'Bearer ' . $token]);

        $response = $this->makeMiddleware()->process($request, $handler);
        $this->assertSame(['ok' => true], $response->getData());
        $this->assertSame(21, $handler->lastRequest->getAttribute('jwt_payload')['uid']);
    }

    public function testInvalidTokenReturns401(): void
    {
        $response = $this->makeMiddleware()->process(
            new \JwtTestPsrRequest('/private', ['Authorization' => 'Bearer bad.token.here']),
            $this->makeHandler()
        );
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(401, $response->getData()['code']);
        $this->assertNotEmpty($response->getData()['msg']);
    }

    public function testBlacklistedTokenReturns401(): void
    {
        $token = $this->jwt->encode(['uid' => 21]);
        $this->jwt->blacklist($token);
        $response = $this->makeMiddleware()->process(
            new \JwtTestPsrRequest('/private', ['Authorization' => 'Bearer ' . $token]),
            $this->makeHandler()
        );
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Token has been blacklisted', $response->getData()['msg']);
    }
}
