<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\FileTokenStorage;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use Firebase\JWT\JWT as FirebaseJWT;
use PHPUnit\Framework\TestCase;

class SignatureSecurityTest extends TestCase
{
    private $validConfig;
    private $tempDir;
    private $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/jwt_sig_test_' . bin2hex(random_bytes(8));
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

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public function testTamperedPayloadRejected(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = $jwt->encode(['uid' => 123]);
        $parts = explode('.', $token);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        $payload['uid'] = 999;
        $tampered = $parts[0] . '.' . $this->base64UrlEncode(json_encode($payload)) . '.' . $parts[2];

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $jwt->decode($tampered);
    }

    public function testWrongKeyRejected(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = FirebaseJWT::encode(
            ['uid' => 1, 'exp' => time() + 3600],
            'a-completely-different-secret-key-32-chars-long',
            'HS256'
        );

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $jwt->decode($token);
    }

    public function testAlgNoneRejected(): void
    {
        $jwt = new JWT($this->validConfig);
        $header = $this->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode(['uid' => 1, 'exp' => time() + 3600]));
        $token = "{$header}.{$payload}.";

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $jwt->decode($token);
    }

    public function testAlgorithmConfusionRejected(): void
    {
        $jwt = new JWT($this->validConfig);
        $token = FirebaseJWT::encode(
            ['uid' => 1, 'exp' => time() + 3600],
            $this->validConfig['secret_key'],
            'HS384'
        );

        $this->expectException(JWTException::class);
        $this->expectExceptionCode(JWTException::TOKEN_INVALID);
        $jwt->decode($token);
    }
}
