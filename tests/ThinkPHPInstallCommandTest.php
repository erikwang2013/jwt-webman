<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\ThinkPHP\InstallCommand;
use think\console\Input;
use think\console\Output;
use PHPUnit\Framework\TestCase;

class ThinkPHPInstallCommandTest extends TestCase
{
    private $configDir;
    private $rootDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->configDir = sys_get_temp_dir() . '/jwt_think_config_' . bin2hex(random_bytes(6));
        $this->rootDir = sys_get_temp_dir() . '/jwt_think_root_' . bin2hex(random_bytes(6));
        mkdir($this->configDir, 0755, true);
        mkdir($this->rootDir, 0755, true);
        $app = new \JwtTestApp();
        $GLOBALS['__jwt_fw']['app'] = $app;
        $GLOBALS['__jwt_fw']['config_path'] = $this->configDir;
        $GLOBALS['__jwt_fw']['root_path'] = $this->rootDir;
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->configDir);
        $this->removeDir($this->rootDir);
    }

    private function invokeCommand(InstallCommand $command): int
    {
        $method = new \ReflectionMethod($command, 'execute');
        $method->setAccessible(true);
        return $method->invoke($command, new Input(), new Output());
    }

    private function configureCommand(InstallCommand $command): void
    {
        $method = new \ReflectionMethod($command, 'configure');
        $method->setAccessible(true);
        $method->invoke($command);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $f) {
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->removeDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function testConfigureSetsNameAndDescription(): void
    {
        $command = new InstallCommand();
        $this->configureCommand($command);
        $this->assertSame('jwt:install', $command->getName());
        $this->assertSame('Install erik JWT: publish config and generate secret key', $command->getDescription());
    }

    public function testExecutePublishesConfigAndWritesEnv(): void
    {
        file_put_contents($this->rootDir . '/.env', "OTHER=1\n");
        $command = new InstallCommand();
        $exit = $this->invokeCommand($command);
        $this->assertSame(0, $exit);

        $dest = $this->configDir . '/jwt.php';
        $this->assertFileExists($dest);
        $this->assertStringContainsString('JWT.SECRET_KEY', file_get_contents($dest));

        $envContent = file_get_contents($this->rootDir . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $envContent);
        $this->assertStringContainsString('OTHER=1', $envContent);

        $output = implode("\n", $GLOBALS['__jwt_fw']['outputs']);
        $this->assertStringContainsString('Config published to', $output);
        $this->assertMatchesRegularExpression('/JWT_SECRET_KEY: [0-9a-f]{64}/', $output);
    }

    public function testExecuteWithoutEnvFileDoesNotCrash(): void
    {
        $command = new InstallCommand();
        $exit = $this->invokeCommand($command);
        $this->assertSame(0, $exit);
        $this->assertFileExists($this->configDir . '/jwt.php');
    }

    public function testExecuteWarnsWhenConfigExists(): void
    {
        file_put_contents($this->configDir . '/jwt.php', '<?php return [];');

        $command = new InstallCommand();
        $this->invokeCommand($command);
        $output = implode("\n", $GLOBALS['__jwt_fw']['outputs']);
        $this->assertStringContainsString('already exists', $output);
    }

    public function testExecuteReplacesExistingSecretKey(): void
    {
        file_put_contents($this->rootDir . '/.env', "JWT_SECRET_KEY=old\nOTHER=1\n");
        $command = new InstallCommand();
        $this->invokeCommand($command);

        $content = file_get_contents($this->rootDir . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $content);
        $this->assertStringNotContainsString('old', $content);
        $this->assertStringContainsString('OTHER=1', $content);
    }
}
