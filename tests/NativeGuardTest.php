<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\FileTokenStorage;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\Native\Guard;
use PHPUnit\Framework\TestCase;

class NativeGuardTest extends TestCase
{
    private $tempDir;
    private $jwt;
    private $guard;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/jwt_guard_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, true);
        $this->jwt = new JWT([
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'algorithm'      => 'HS256',
            'issuer'         => 'test-issuer',
            'audience'       => 'test-audience',
            'default_expire' => 3600,
            'refresh_expire' => 7200,
            '_token_storage' => new FileTokenStorage($this->tempDir . '/bl'),
        ]);
        $this->guard = new Guard($this->jwt);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        if (is_dir($this->tempDir)) {
            remove_dir($this->tempDir);
        }
    }

    public function testAuthenticateWithExplicitToken(): void
    {
        $payload = $this->guard->authenticate($this->jwt->encode(['user_id' => 5]));

        $this->assertSame(5, $payload['user_id']);
        $this->assertArrayHasKey('jti', $payload);
    }

    public function testAuthenticateReadsRequestHeader(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->jwt->encode(['user_id' => 6]);

        $this->assertSame(6, $this->guard->authenticate()['user_id']);
    }

    public function testAuthenticateThrowsWithoutToken(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $this->guard->authenticate();
    }

    public function testCheckDoesNotThrow(): void
    {
        $this->assertFalse($this->guard->check('not-a-token'));
        $this->assertTrue($this->guard->check($this->jwt->encode(['user_id' => 1])));
    }

    public function testRequireAuthReturnsPayloadForValidToken(): void
    {
        $this->assertSame(8, $this->guard->requireAuth($this->jwt->encode(['user_id' => 8]))['user_id']);
    }

    public function testRespondOutputsSameShapeAsFrameworkMiddleware(): void
    {
        ob_start();
        Guard::respond(JWTException::blacklisted());
        $body = ob_get_clean();

        $decoded = json_decode($body, true);
        $this->assertSame(401, $decoded['code']);
        $this->assertSame('Token has been blacklisted', $decoded['msg']);
        $this->assertNull($decoded['data']);
    }

    public function testFromFileBuildsGuardFromConfigFile(): void
    {
        $file = $this->tempDir . '/jwt.php';
        file_put_contents($file, '<?php return ' . var_export([
            'secret_key' => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'storage'    => ['type' => 'file', 'path' => $this->tempDir . '/bl2'],
        ], true) . ';');

        $guard = Guard::fromFile($file);
        $token = $guard->getJWT()->encode(['user_id' => 3]);

        $this->assertSame(3, $guard->requireAuth($token)['user_id']);
    }

    public function testBlacklistedTokenIsRejected(): void
    {
        $token = $this->jwt->encode(['user_id' => 4]);
        $this->jwt->blacklist($token);

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_BLACKLISTED);
        $this->guard->authenticate($token);
    }
}
