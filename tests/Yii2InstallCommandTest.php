<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
require_once __DIR__ . '/FrameworkStubs.php';
use Erikwang2013\Jwt\Yii2\InstallController;
use PHPUnit\Framework\TestCase;
use Yii;

class Yii2InstallCommandTest extends TestCase
{
    private $tempDir;

    protected function setUp(): void
    {
        jwt_fw_reset();
        Yii::$aliases = [];
        $this->tempDir = sys_get_temp_dir() . '/jwt_yii2_install_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/config', 0755, true);
        Yii::$app = new \JwtTestYiiApp();
        Yii::setAlias('@app', $this->tempDir);
    }

    protected function tearDown(): void
    {
        Yii::$app = null;
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = "{$dir}/{$entry}";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function outputs(): array
    {
        return $GLOBALS['__jwt_fw']['outputs'];
    }

    public function testPublishesConfigAndWritesSecret(): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_ENV=test\n");

        $code = (new InstallController())->actionIndex();

        $this->assertSame(0, $code);

        $published = $this->tempDir . '/config/jwt.php';
        $this->assertFileExists($published);
        $this->assertIsArray(require $published);
        $this->assertFileExists($this->tempDir . '/config/jwt.php');

        $env = file_get_contents($this->tempDir . '/.env');
        $this->assertMatchesRegularExpression('/^JWT_SECRET_KEY=[0-9a-f]{64}$/m', $env);
        $this->assertStringContainsString('APP_ENV=test', $env);
    }

    public function testExistingConfigIsNotOverwritten(): void
    {
        file_put_contents($this->tempDir . '/config/jwt.php', "<?php return ['custom' => true];\n");
        file_put_contents($this->tempDir . '/.env', "APP_ENV=test\n");

        (new InstallController())->actionIndex();

        $this->assertSame(['custom' => true], require $this->tempDir . '/config/jwt.php');
        $this->assertStringContainsString('already exists', implode("\n", $this->outputs()));
    }

    public function testMissingEnvFallsBackToPrintingTheKey(): void
    {
        $code = (new InstallController())->actionIndex();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('not writable', implode("\n", $this->outputs()));
        $this->assertMatchesRegularExpression(
            '/JWT_SECRET_KEY=[0-9a-f]{64}/',
            implode("\n", $this->outputs())
        );
        $this->assertFileDoesNotExist($this->tempDir . '/.env');
    }
}
