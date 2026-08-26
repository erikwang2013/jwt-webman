<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Install;
use PHPUnit\Framework\TestCase;

class InstallTest extends TestCase
{
    private $baseDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        $this->baseDir = sys_get_temp_dir() . '/jwt_install_' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0755, true);
        $GLOBALS['__jwt_fw']['base_path'] = $this->baseDir;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->baseDir)) {
            remove_dir($this->baseDir);
        }
    }

    private function destDir(): string
    {
        return $this->baseDir . '/config/plugin/erikwang2013/jwt';
    }

    private function sourceDir(): string
    {
        return __DIR__ . '/../src/erik-jwt/config/plugin/erikwang2013/jwt';
    }

    public function testPluginConstant(): void
    {
        $this->assertTrue(Install::WEBMAN_PLUGIN);
    }

    public function testInstallCopiesConfigFiles(): void
    {
        ob_start();
        Install::install();
        ob_end_clean();

        $this->assertFileExists($this->destDir() . '/app.php');
        $this->assertFileExists($this->destDir() . '/jwt.php');
        $this->assertSame(
            file_get_contents($this->sourceDir() . '/jwt.php'),
            file_get_contents($this->destDir() . '/jwt.php')
        );
    }

    public function testInstallIsIdempotent(): void
    {
        ob_start();
        Install::install();
        Install::install();
        ob_end_clean();

        $this->assertFileExists($this->destDir() . '/jwt.php');
    }

    public function testUninstallRemovesCopiedConfig(): void
    {
        ob_start();
        Install::install();
        Install::uninstall();
        ob_end_clean();

        $this->assertDirectoryDoesNotExist($this->destDir());
    }

    public function testUninstallWithoutInstallDoesNotThrow(): void
    {
        ob_start();
        Install::uninstall();
        ob_end_clean();
        $this->assertTrue(true);
    }
}
