<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Yii2\JwtAuth;
use Erikwang2013\Jwt\Yii2\JwtIdentity;
use Erikwang2013\Jwt\Yii2\JwtService;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\UnknownPropertyException;
use yii\web\Request;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\User;

class Yii2MiddlewareTest extends TestCase
{
    private $tempDir;
    private $service;
    private $config;
    private $user;
    private $response;

    protected function setUp(): void
    {
        jwt_fw_reset();
        Yii::$aliases = [];
        $this->tempDir = sys_get_temp_dir() . '/jwt_yii2_mw_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);

        $this->config = [
            'secret_key'     => 'this-is-a-very-secure-secret-key-for-testing-256bits',
            'default_expire' => 3600,
            'issuer'         => 'test',
            'audience'       => 'test',
            'storage'        => ['type' => 'file', 'path' => $this->tempDir, 'gc_probability' => 0],
        ];

        $this->service  = new JwtService(['config' => $this->config]);
        $this->user     = new User();
        $this->response = new Response();

        Yii::$app = new \JwtTestYiiApp([], [
            'jwt'      => $this->service,
            'user'     => $this->user,
            'request'  => new Request(),
            'response' => $this->response,
        ]);
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

    private function authenticate(array $headers, array $filterConfig = []): void
    {
        (new JwtAuth($filterConfig))->authenticate($this->user, new Request($headers), $this->response);
    }

    public function testMissingTokenThrows401(): void
    {
        try {
            $this->authenticate([]);
            $this->fail('expected UnauthorizedHttpException');
        } catch (UnauthorizedHttpException $e) {
            $this->assertSame(401, $e->statusCode);
            $this->assertSame('Token not provided', $e->getMessage());
        }
    }

    public function testMalformedTokenThrows401(): void
    {
        $this->expectException(UnauthorizedHttpException::class);
        $this->authenticate(['Authorization' => 'Bearer not.a.token']);
    }

    public function testExpiredTokenThrows401WithUserMessage(): void
    {
        $token = $this->service->encode(['uid' => 1], -50);

        try {
            $this->authenticate(['Authorization' => 'Bearer ' . $token]);
            $this->fail('expected UnauthorizedHttpException');
        } catch (UnauthorizedHttpException $e) {
            $this->assertSame(401, $e->statusCode);
            $this->assertSame('Token has expired', $e->getMessage());
        }
    }

    public function testRefreshTokenIsRejectedAsAccessToken(): void
    {
        $token = $this->service->encode(['uid' => 1, 'token_type' => 'refresh']);

        $this->expectException(UnauthorizedHttpException::class);
        $this->authenticate(['Authorization' => 'Bearer ' . $token]);
    }

    public function testBlacklistedTokenThrows401(): void
    {
        $token = $this->service->encode(['uid' => 1]);
        $this->service->blacklist($token);

        $this->expectException(UnauthorizedHttpException::class);
        $this->authenticate(['Authorization' => 'Bearer ' . $token]);
    }

    public function testValidTokenReturnsIdentityCarryingThePayload(): void
    {
        $token  = $this->service->encode(['sub' => 'user-7', 'role' => 'admin']);
        $filter = new JwtAuth();
        $request = new Request(['Authorization' => 'Bearer ' . $token]);

        $identity = $filter->authenticate($this->user, $request, $this->response);

        $this->assertInstanceOf(JwtIdentity::class, $identity);
        $this->assertSame('user-7', $identity->getId());
        $this->assertSame('admin', $identity->payload['role']);
        $this->assertSame('test', $identity->payload['iss']);
    }

    /**
     * 锁死"payload 只能挂在身份对象上"这条约束。
     *
     * 真实 Yii2 的 yii\base\Request 继承自 Component，Component::__set() 对未声明属性
     * 直接抛 UnknownPropertyException（不是发弃用警告），所以 $request->jwt_payload = ...
     * 会让每个认证成功的请求 500。桩必须保留这个行为，否则测试又会把它盖住。
     */
    public function testRequestRejectsDynamicProperties(): void
    {
        $this->expectException(UnknownPropertyException::class);

        $request = new Request();
        $request->jwt_payload = ['sub' => 'user-7'];
    }

    public function testIdentityIsLoggedIntoUserComponent(): void
    {
        $token  = $this->service->encode(['sub' => 'user-7']);
        $filter = new JwtAuth();
        $request = new Request(['Authorization' => 'Bearer ' . $token]);

        $this->assertTrue($this->user->getIsGuest());
        $filter->authenticate($this->user, $request, $this->response);

        $this->assertFalse($this->user->getIsGuest());
        $this->assertSame('user-7', $this->user->getIdentity()->getId());
    }

    public function testFailureSetsWwwAuthenticateHeader(): void
    {
        try {
            $this->authenticate(['Authorization' => 'Bearer bad.token.here']);
        } catch (UnauthorizedHttpException $e) {
            // 继续断言响应头
        }

        $this->assertSame('Bearer realm="api"', $this->response->getHeaders()->get('WWW-Authenticate'));
    }

    public function testOptionalActionIsAllowedWithoutToken(): void
    {
        $filter = new JwtAuth(['optional' => ['public']]);

        $this->assertTrue($filter->beforeAction('public'));
    }

    public function testNonOptionalActionStillRejectsWithoutToken(): void
    {
        $filter = new JwtAuth(['optional' => ['public']]);

        $this->expectException(UnauthorizedHttpException::class);
        $filter->beforeAction('private');
    }

    public function testOptionalActionStillParsesAValidToken(): void
    {
        $token = $this->service->encode(['sub' => 'user-7']);
        Yii::$app->set('request', new Request(['Authorization' => 'Bearer ' . $token]));

        $filter = new JwtAuth(['optional' => ['public']]);

        $this->assertTrue($filter->beforeAction('public'));
        $this->assertSame('user-7', Yii::$app->getUser()->getIdentity()->payload['sub']);
    }

    public function testMissingJwtComponentRaisesInvalidConfig(): void
    {
        Yii::$app->set('jwt', new \stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->authenticate(['Authorization' => 'Bearer whatever']);
    }
}
