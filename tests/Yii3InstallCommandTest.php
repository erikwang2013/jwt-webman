<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Yii3\InstallCommand;
use PHPUnit\Framework\TestCase;

class Yii3InstallCommandTest extends TestCase
{
    private $tempDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->tempDir = sys_get_temp_dir() . '/jwt_yii3_install_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
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

    private function outputs(): array
    {
        return $GLOBALS['__jwt_fw']['outputs'];
    }

    private function runCommand(): int
    {
        return (new InstallCommand($this->tempDir))
            ->run(new \JwtTestSymfonyInput(), new \JwtTestSymfonyOutput());
    }

    public function testCommandNameComesFromTheAsCommandAttribute(): void
    {
        $this->assertSame('jwt:install', (new InstallCommand())->getName());
    }

    public function testWritesSecretToEnv(): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_ENV=test\n");

        $this->assertSame(0, $this->runCommand());

        $env = file_get_contents($this->tempDir . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $env);
        $this->assertStringContainsString('APP_ENV=test', $env);
        $this->assertStringContainsString('installed successfully', implode("\n", $this->outputs()));
    }

    public function testReplacesAnExistingSecretRatherThanAppending(): void
    {
        file_put_contents($this->tempDir . '/.env', "JWT_SECRET_KEY=old\n");

        $this->runCommand();

        $env = file_get_contents($this->tempDir . '/.env');
        $this->assertStringNotContainsString('old', $env);
        $this->assertSame(1, substr_count($env, 'JWT_SECRET_KEY='));
    }

    public function testMissingEnvFallsBackToPrintingTheKey(): void
    {
        $this->assertSame(0, $this->runCommand());

        $output = implode("\n", $this->outputs());
        $this->assertStringContainsString('not writable', $output);
        $this->assertMatchesRegularExpression('/JWT_SECRET_KEY=[0-9a-f]{64}/', $output);
        $this->assertFileDoesNotExist($this->tempDir . '/.env');
    }

    public function testConsoleParamsRegisterTheCommand(): void
    {
        $params = require __DIR__ . '/../src/erik-jwt/Yii3/config/params-console.php';

        $this->assertSame(
            InstallCommand::class,
            $params['yiisoft/yii-console']['commands']['jwt:install'] ?? null
        );
    }
}
