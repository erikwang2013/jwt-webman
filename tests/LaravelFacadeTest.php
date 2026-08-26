<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\FileTokenStorage;
use Erikwang2013\Jwt\Laravel\Facade;
use PHPUnit\Framework\TestCase;

class LaravelFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        jwt_fw_reset();
    }

    public function testFacadeAccessor(): void
    {
        $method = new \ReflectionMethod(Facade::class, 'getFacadeAccessor');
        $this->assertSame('erik.jwt', $method->invoke(null));
    }

    public function testFacadeExtendsLaravelFacade(): void
    {
        $this->assertTrue(is_subclass_of(Facade::class, \Illuminate\Support\Facades\Facade::class));
    }

    public function testJwtHelperReturnsBoundInstance(): void
    {
        $this->assertTrue(function_exists('jwt'));
        $tempDir = sys_get_temp_dir() . '/jwt_helpers_' . bin2hex(random_bytes(6));
        mkdir($tempDir, 0755, true);
        try {
            $config = [
                'secret_key' => 'this-is-a-very-secure-secret-key-for-testing-256bits',
                '_token_storage' => new FileTokenStorage($tempDir),
            ];
            $jwt = new JWT($config);
            $GLOBALS['__jwt_fw']['bindings']['erik.jwt'] = $jwt;
            $this->assertSame($jwt, jwt());
            $token = jwt()->encode(['uid' => 1]);
            $this->assertIsString($token);
        } finally {
            $files = array_diff(scandir($tempDir), ['.', '..']);
            foreach ($files as $f) {
                unlink("{$tempDir}/{$f}");
            }
            rmdir($tempDir);
        }
    }
}
