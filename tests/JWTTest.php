<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\FileTokenStorage;
use Firebase\JWT\JWT as FirebaseJWT;
use PHPUnit\Framework\TestCase;

class JWTTest extends TestCase
{
    private $validConfig;
    private $tempDir;
    private $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/jwt_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileTokenStorage($this->tempDir);
        $this->validConfig = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'algorithm'      => 'HS256',
            'issuer'         => 'test-issuer',
            'audience'       => 'test-audience',
            'leeway'         => 0,
            'default_expire' => 3600,
            'refresh_expire' => 7200,
            '_token_storage' => $this->storage,
        ];
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = array_diff(scandir($this->tempDir), ['.', '..']);
            foreach ($files as $f) { unlink("{$this->tempDir}/{$f}"); }
            rmdir($this->tempDir);
        }
    }

    public function testEncodeDecode(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 123, 'role' => 'admin']);
        $this->assertIsString($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $payload = $jwt->decode($token);
        $this->assertSame(123, $payload['uid']);
        $this->assertSame('admin', $payload['role']);
    }

    public function testEncodeWithCustomExpire(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1], 1800);
        $payload = $jwt->decode($token);
        $this->assertSame(1, $payload['uid']);
    }

    public function testPayloadContainsStandardClaims(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1]);
        $payload = $jwt->decode($token);
        $this->assertArrayHasKey('iss', $payload);
        $this->assertArrayHasKey('aud', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('nbf', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertSame('test-issuer', $payload['iss']);
        $this->assertSame('test-audience', $payload['aud']);
    }

    public function testEncodeWithHeaders(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1], 3600, ['kid' => 'key-1']);
        $payload = $jwt->decode($token);
        $this->assertSame(1, $payload['uid']);
    }

    public function testEncodeRefreshToken(): void
    {
        $config = $this->validConfig;
        $config['refresh_expire'] = 14400;
        $jwt = new JWT($config);
        $token = $jwt->encode(['uid' => 1, 'token_type' => 'refresh']);
        $payload = $jwt->decode($token);
        $this->assertSame(1, $payload['uid']);
        $this->assertSame('refresh', $payload['token_type']);
    }

    public function testValidateValidToken(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1]);
        $this->assertTrue($jwt->validate($token));
    }

    public function testValidateInvalidToken(): void
    {
        $jwt = new JWT($this->validConfig);
        $this->assertFalse($jwt->validate('invalid.token.string'));
    }

    public function testDecodeExpiredTokenThrows(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1], -7200);

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_EXPIRED);
        $jwt->decode($token);
    }

    public function testDecodeInvalidTokenThrows(): void
    {
        $jwt = new JWT($this->validConfig);
        $this->expectException(JWTException::class);
        $jwt->decode('invalid.token.here');
    }

    public function testBlacklistAndCheck(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1]);
        $this->assertTrue($jwt->blacklist($token));
        $this->assertTrue($jwt->isBlacklisted($token));
    }

    public function testDecodeBlacklistedTokenThrows(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1]);
        $jwt->blacklist($token);

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_BLACKLISTED);
        $jwt->decode($token);
    }

    public function testRefresh(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1, 'token_type' => 'refresh']);
        $newToken = $jwt->refresh($token, 3600);

        $this->assertNotSame($token, $newToken);
        $payload = $jwt->decode($newToken);
        $this->assertSame(1, $payload['uid']);
    }

    public function testRefreshBlacklistsOldToken(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1, 'token_type' => 'refresh']);
        $jwt->refresh($token);

        $this->assertTrue($jwt->isBlacklisted($token));
    }

    public function testGetPayloadWithoutValidation(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 42, 'name' => 'test']);
        $payload = $jwt->getPayloadWithoutValidation($token);
        $this->assertSame(42, $payload['uid']);
        $this->assertSame('test', $payload['name']);
    }

    public function testGetPayloadInvalidStructure(): void
    {
        $jwt = new JWT($this->validConfig);
        $this->expectException(JWTException::class);
        $jwt->getPayloadWithoutValidation('not-a-jwt');
    }

    public function testGetAlgorithm(): void
    {
        $jwt = new JWT($this->validConfig);
        $this->assertSame('HS256', $jwt->getAlgorithm());
    }

    public function testCleanup(): void
    {
        $jwt = new JWT($this->validConfig);
        $this->assertTrue($jwt->cleanup());
    }

    public function testSetTokenStorage(): void
    {
        $jwt = new JWT($this->validConfig);
        $newStorage = new FileTokenStorage($this->tempDir . '_alt');
        @mkdir($this->tempDir . '_alt', 0755, true);
        $jwt->setTokenStorage($newStorage);
        $this->assertTrue(true);
        @rmdir($this->tempDir . '_alt');
    }

    public function testBlacklistAlreadyBlacklistedToken(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1]);
        $jwt->blacklist($token);
        // Try to blacklist again - should not throw
        $result = $jwt->blacklist($token);
        $this->assertIsBool($result);
    }

    public function testIsBlacklistedInvalidToken(): void
    {
        $jwt = new JWT($this->validConfig);
        $this->assertFalse($jwt->isBlacklisted('invalid.token.here'));
    }

    public function testDecodeRejectsWrongIssuer(): void
    {
        $config = $this->validConfig;
        $config['issuer'] = 'issuer-a';
        $jwt = new JWT($config);
        $token = FirebaseJWT::encode(
            ['uid' => 1, 'exp' => time() + 3600, 'iss' => 'issuer-b'],
            $config['secret_key'],
            'HS256'
        );

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $jwt->decode($token);
    }

    public function testDecodeAcceptsMatchingIssuer(): void
    {
        $config = $this->validConfig;
        $config['issuer'] = 'issuer-a';
        $jwt = new JWT($config);
        $token = $jwt->encode(['uid' => 1]);
        $payload = $jwt->decode($token);
        $this->assertSame('issuer-a', $payload['iss']);
    }

    public function testDecodeRejectsWrongAudience(): void
    {
        $config = $this->validConfig;
        $config['audience'] = 'audience-a';
        $jwt = new JWT($config);
        $token = FirebaseJWT::encode(
            ['uid' => 1, 'exp' => time() + 3600, 'aud' => 'audience-b'],
            $config['secret_key'],
            'HS256'
        );

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $jwt->decode($token);
    }

    public function testDecodeAcceptsMatchingAudience(): void
    {
        $config = $this->validConfig;
        $config['audience'] = 'audience-a';
        $jwt = new JWT($config);
        $token = $jwt->encode(['uid' => 1]);
        $payload = $jwt->decode($token);
        $this->assertSame('audience-a', $payload['aud']);
    }

    public function testEmptyIssuerConfigAllowsMissingIss(): void
    {
        $config = $this->validConfig;
        $config['issuer'] = '';
        $config['audience'] = '';
        $jwt = new JWT($config);
        $token = FirebaseJWT::encode(
            ['uid' => 1, 'exp' => time() + 3600],
            $config['secret_key'],
            'HS256'
        );
        $payload = $jwt->decode($token);
        $this->assertSame(1, $payload['uid']);
    }

    public function testEncodeIgnoresProtectedClaims(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1, 'exp' => time() + 999999, 'jti' => 'aaaa']);
        $payload = $jwt->decode($token);

        $expected = time() + $this->validConfig['default_expire'];
        $this->assertGreaterThanOrEqual($expected - 5, $payload['exp']);
        $this->assertLessThanOrEqual($expected + 5, $payload['exp']);
        $this->assertNotSame('aaaa', $payload['jti']);
        $this->assertSame(32, strlen($payload['jti']));
        $this->assertTrue(ctype_xdigit($payload['jti']));
    }

    public function testRefreshRejectsAccessToken(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1]);

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $this->expectExceptionMessage('Only refresh tokens can be refreshed');
        $jwt->refresh($token);
    }

    public function testDecodeWithLeewayAcceptsRecentlyExpiredToken(): void
    {
        $config = $this->validConfig;
        $config['leeway'] = 60;
        $jwt = new JWT($config);
        $token = $jwt->encode(['uid' => 1], -30);
        $payload = $jwt->decode($token);
        $this->assertSame(1, $payload['uid']);
    }

    public function testBlacklistTokenWithoutJtiReturnsFalse(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = FirebaseJWT::encode(
            ['uid' => 1, 'exp' => time() + 3600, 'iss' => 'test-issuer', 'aud' => 'test-audience'],
            $this->validConfig['secret_key'],
            'HS256'
        );
        $this->assertFalse($jwt->blacklist($token));
    }

    public function testBlacklistExpiredTokenReturnsTrue(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1], -7200);
        $this->assertTrue($jwt->blacklist($token));
    }

    public function testIsBlacklistedExpiredBlacklistedTokenReturnsFalse(): void
    {
        // An expired token is reported as expired, not as blacklisted
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 1], -7200);
        $this->assertTrue($jwt->blacklist($token));
        $this->assertFalse($jwt->isBlacklisted($token));
    }
}
