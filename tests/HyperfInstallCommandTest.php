<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Hyperf\InstallCommand;
use PHPUnit\Framework\TestCase;

class HyperfInstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        jwt_fw_reset();
        if (is_dir(BASE_PATH)) {
            remove_dir(BASE_PATH);
        }
        mkdir(BASE_PATH . '/config/autoload', 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir(BASE_PATH)) {
            remove_dir(BASE_PATH);
        }
    }

    public function testConfigureSetsDescription(): void
    {
        $command = new InstallCommand(new \JwtTestContainer());
        $command->configure();
        $this->assertSame('jwt:install', $command->getName());
        $this->assertSame('Install erik JWT: publish config and generate secret key', $command->getDescription());
    }

    public function testHandlePublishesConfigAndWritesEnv(): void
    {
        file_put_contents(BASE_PATH . '/.env', "OTHER=1\n");
        $command = new InstallCommand(new \JwtTestContainer());
        $command->handle();

        $dest = BASE_PATH . '/config/autoload/jwt.php';
        $this->assertFileExists($dest);
        $this->assertStringContainsString('JWT_SECRET_KEY', file_get_contents($dest));

        $envContent = file_get_contents(BASE_PATH . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $envContent);
        $this->assertStringContainsString('OTHER=1', $envContent);

        $output = implode("\n", $GLOBALS['__jwt_fw']['outputs']);
        $this->assertStringContainsString('Config published to', $output);
        $this->assertMatchesRegularExpression('/JWT_SECRET_KEY: [0-9a-f]{64}/', $output);
    }

    public function testHandleWithoutEnvFileDoesNotCrash(): void
    {
        $command = new InstallCommand(new \JwtTestContainer());
        $command->handle();
        $this->assertFileExists(BASE_PATH . '/config/autoload/jwt.php');
    }

    public function testHandleWarnsWhenConfigExists(): void
    {
        file_put_contents(BASE_PATH . '/config/autoload/jwt.php', '<?php return [];');
        $command = new InstallCommand(new \JwtTestContainer());
        $command->handle();
        $this->assertStringContainsString('already exists', implode("\n", $GLOBALS['__jwt_fw']['outputs']));
    }

    public function testHandleReplacesExistingSecretKey(): void
    {
        file_put_contents(BASE_PATH . '/.env', "JWT_SECRET_KEY=old\nOTHER=1\n");
        $command = new InstallCommand(new \JwtTestContainer());
        $command->handle();

        $content = file_get_contents(BASE_PATH . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $content);
        $this->assertStringNotContainsString('old', $content);
        $this->assertStringContainsString('OTHER=1', $content);
    }
}
