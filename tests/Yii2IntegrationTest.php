<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\JWTException;
use Erikwang2013\Jwt\Yii2\JwtIdentity;
use Erikwang2013\Jwt\Yii2\JwtService;
use PHPUnit\Framework\TestCase;
use Yii;

class Yii2IntegrationTest extends TestCase
{
    private $tempDir;
    private $jwtConfig;

    protected function setUp(): void
    {
        jwt_fw_reset();
        Yii::$aliases = [];
        $this->tempDir = sys_get_temp_dir() . '/jwt_yii2_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);

        $this->jwtConfig = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            'storage'        => ['type' => 'file', 'path' => $this->tempDir, 'gc_probability' => 0],
        ];

        Yii::$app = new \JwtTestYiiApp(['jwt' => $this->jwtConfig]);
        Yii::setAlias('@app', $this->tempDir);
    }

    protected function tearDown(): void
    {
        Yii::$app = null;
        if (is_dir($this->tempDir)) {
            foreach (array_diff(scandir($this->tempDir), ['.', '..']) as $f) {
                unlink("{$this->tempDir}/{$f}");
            }
            rmdir($this->tempDir);
        }
    }

    public function testComponentFallsBackToParamsWhenConfigLeftEmpty(): void
    {
        $service = new JwtService();
        $this->assertTrue($service->validate($service->encode(['uid' => 42])));
    }

    public function testInjectedConfigWinsOverParams(): void
    {
        // params 里是一个坏密钥，注入的 config 必须是生效的那个
        Yii::$app->params['jwt'] = ['secret_key' => ''];

        $service = new JwtService(['config' => $this->jwtConfig]);
        $payload = $service->decode($service->encode(['uid' => 7]));

        $this->assertSame(7, $payload['uid']);
        $this->assertSame('test', $payload['iss']);
    }

    public function testDecodeRejectsMissingSecret(): void
    {
        $this->expectException(JWTException::class);

        (new JwtService(['config' => ['secret_key' => '']]))->encode(['uid' => 1]);
    }

    public function testRefreshRotatesTokenAndBlacklistsTheOldOne(): void
    {
        $service = new JwtService(['config' => $this->jwtConfig]);

        $refresh = $service->encode(['uid' => 5, 'token_type' => 'refresh']);
        $new     = $service->refresh($refresh);

        $this->assertNotSame($refresh, $new);
        $this->assertSame(5, $service->decode($new, true)['uid']);
        // 刷新时旧令牌进黑名单
        $this->assertTrue($service->isBlacklisted($refresh));
        $this->assertFalse($service->validate($refresh, true));
    }

    public function testBlacklistMakesAccessTokenInvalid(): void
    {
        $service = new JwtService(['config' => $this->jwtConfig]);

        $token = $service->encode(['uid' => 1]);
        $this->assertTrue($service->validate($token));
        $this->assertTrue($service->blacklist($token));
        $this->assertFalse($service->validate($token));
    }

    public function testIdentityReadsSubjectClaim(): void
    {
        $identity = new JwtIdentity(['payload' => ['sub' => 'user-9', 'jti' => 'abc']]);

        $this->assertSame('user-9', $identity->getId());
        $this->assertSame('abc', $identity->getAuthKey());
        $this->assertTrue($identity->validateAuthKey('abc'));
        $this->assertFalse($identity->validateAuthKey('nope'));
    }

    public function testIdentityFallsBackToUidThenIdClaim(): void
    {
        $this->assertSame('11', (new JwtIdentity(['payload' => ['uid' => 11]]))->getId());
        $this->assertSame('22', (new JwtIdentity(['payload' => ['id' => 22]]))->getId());
        $this->assertSame('', (new JwtIdentity(['payload' => []]))->getId());
    }

    public function testIdentityRejectsEmptyAuthKey(): void
    {
        $identity = new JwtIdentity(['payload' => ['sub' => 'u']]);

        $this->assertSame('', $identity->getAuthKey());
        // 没有 jti 时不能拿空串冒充通过
        $this->assertFalse($identity->validateAuthKey(''));
    }

    public function testIdentityFindersAreStateless(): void
    {
        $this->assertNull(JwtIdentity::findIdentity('1'));
        $this->assertNull(JwtIdentity::findIdentityByAccessToken('token', JwtIdentity::class));
    }
}
