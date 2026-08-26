<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Laravel\InstallCommand;
use PHPUnit\Framework\TestCase;

class LaravelInstallCommandTest extends TestCase
{
    private $baseDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->baseDir = sys_get_temp_dir() . '/jwt_laravel_install_' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0755, true);
        $GLOBALS['__jwt_fw']['base_path'] = $this->baseDir;
    }

    protected function tearDown(): void
    {
        $files = array_diff(scandir($this->baseDir), ['.', '..']);
        foreach ($files as $f) {
            unlink("{$this->baseDir}/{$f}");
        }
        rmdir($this->baseDir);
    }

    public function testHandleReturnsZeroAndCallsVendorPublish(): void
    {
        $command = new InstallCommand();
        $this->assertSame('jwt:install', $command->getSignature());
        $this->assertSame(0, $command->handle());

        $this->assertCount(1, $GLOBALS['__jwt_fw']['called']);
        $this->assertSame('vendor:publish', $GLOBALS['__jwt_fw']['called'][0]['command']);
        $this->assertSame(['--tag' => 'jwt-config'], $GLOBALS['__jwt_fw']['called'][0]['parameters']);
        $this->assertStringContainsString('JWT plugin installed successfully!', implode("\n", $GLOBALS['__jwt_fw']['outputs']));
    }

    public function testHandleAppendsSecretKeyToEnv(): void
    {
        file_put_contents($this->baseDir . '/.env', "APP_KEY=base64:abc\n");
        $command = new InstallCommand();
        $command->handle();

        $content = file_get_contents($this->baseDir . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $content);
        $this->assertStringContainsString("APP_KEY=base64:abc\n", $content);
    }

    public function testHandleReplacesExistingSecretKey(): void
    {
        file_put_contents($this->baseDir . '/.env', "JWT_SECRET_KEY=old-key-value\nOTHER=1\n");
        $command = new InstallCommand();
        $command->handle();

        $content = file_get_contents($this->baseDir . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $content);
        $this->assertStringNotContainsString('old-key-value', $content);
        $this->assertStringContainsString('OTHER=1', $content);
    }

    public function testHandleWithoutEnvFileDoesNotCrash(): void
    {
        $command = new InstallCommand();
        $this->assertSame(0, $command->handle());
        $this->assertFileDoesNotExist($this->baseDir . '/.env');
    }

    public function testGeneratedSecretIsPrintedToOutput(): void
    {
        $command = new InstallCommand();
        $command->handle();
        $output = implode("\n", $GLOBALS['__jwt_fw']['outputs']);
        $this->assertMatchesRegularExpression('/JWT_SECRET_KEY: [0-9a-f]{64}/', $output);
    }
}
