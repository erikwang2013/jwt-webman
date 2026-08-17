<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\JwtWrapper;
use Erikwang2013\Jwt\FileTokenStorage;
use PHPUnit\Framework\TestCase;

class JwtWrapperTest extends TestCase
{
    private $validConfig;
    private $tempDir;
    private $jwt;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/jwt_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, true);
        $this->validConfig = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'algorithm'      => 'HS256',
            'issuer'         => 'test-issuer',
            'audience'       => 'test-audience',
            'leeway'         => 0,
            'default_expire' => 3600,
            'refresh_expire' => 7200,
            '_token_storage' => new FileTokenStorage($this->tempDir),
        ];
        $this->jwt = new JwtWrapper(new JWT($this->validConfig));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = array_diff(scandir($this->tempDir), ['.', '..']);
            foreach ($files as $f) { unlink("{$this->tempDir}/{$f}"); }
            rmdir($this->tempDir);
        }
    }

    public function testCreate(): void
    {
        $token = $this->jwt->create(['uid' => 1]);
        $this->assertIsString($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testCreateWithCustomExpire(): void
    {
        $token = $this->jwt->create(['uid' => 1], 1800);
        $payload = $this->jwt->decode($token);
        $this->assertSame(1, $payload['uid']);
    }

    public function testVerify(): void
    {
        $token = $this->jwt->create(['uid' => 42, 'role' => 'admin']);
        $payload = $this->jwt->verify($token);
        $this->assertIsObject($payload);
        $this->assertSame(42, $payload->uid);
        $this->assertSame('admin', $payload->role);
    }

    public function testVerifyInvalidTokenThrows(): void
    {
        $this->expectException(JWTException::class);
        $this->jwt->verify('invalid.token.here');
    }

    public function testDecode(): void
    {
        $token = $this->jwt->create(['uid' => 99]);
        $payload = $this->jwt->decode($token);
        $this->assertSame(99, $payload['uid']);
    }

    public function testValidate(): void
    {
        $token = $this->jwt->create(['uid' => 1]);
        $this->assertTrue($this->jwt->validate($token));
        $this->assertFalse($this->jwt->validate('bad.token.here'));
    }

    public function testBlacklist(): void
    {
        $token = $this->jwt->create(['uid' => 1]);
        $this->assertTrue($this->jwt->blacklist($token));
    }

    public function testIsBlacklisted(): void
    {
        $token = $this->jwt->create(['uid' => 1]);
        $this->assertFalse($this->jwt->isBlacklisted($token));
        $this->jwt->blacklist($token);
        $this->assertTrue($this->jwt->isBlacklisted($token));
    }

    public function testRefresh(): void
    {
        $token = $this->jwt->create(['uid' => 1, 'token_type' => 'refresh']);
        $newToken = $this->jwt->refresh($token);
        $this->assertNotSame($token, $newToken);
        $payload = $this->jwt->decode($newToken);
        $this->assertSame(1, $payload['uid']);
    }

    public function testRefreshBlacklistsOldToken(): void
    {
        $token = $this->jwt->create(['uid' => 1, 'token_type' => 'refresh']);
        $this->jwt->refresh($token);
        $this->assertTrue($this->jwt->isBlacklisted($token));
    }

    public function testRefreshWithoutTokenThrowsWhenNoHeader(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $this->expectException(JWTException::class);
        $this->jwt->refresh();
    }

    public function testRefreshWithBearerHeader(): void
    {
        $token = $this->jwt->create(['uid' => 1, 'token_type' => 'refresh']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        $newToken = $this->jwt->refresh();
        $this->assertNotSame($token, $newToken);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
}
