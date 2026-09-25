<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\Mascot;
use PHPUnit\Framework\TestCase;

class MascotTest extends TestCase
{
    public function testBannerPlainTextHasNoAnsi(): void
    {
        $banner = Mascot::banner(false);
        $this->assertStringContainsString(Mascot::NAME, $banner);
        $this->assertStringContainsString('erikwang2013/jwt-webman', $banner);
        $this->assertDoesNotMatchRegularExpression('/\033\[/', $banner);
    }

    public function testBannerColoredHasAnsi(): void
    {
        $this->assertMatchesRegularExpression('/\033\[/', Mascot::banner(true));
        // 着色与纯文本的可见内容一致
        $plain = preg_replace('/\033\[[0-9;]*m/', '', Mascot::banner(true));
        $this->assertSame(Mascot::banner(false), $plain);
    }

    public function testSvgIsWellFormedAndInert(): void
    {
        $svg = Mascot::svg();
        $this->assertNotSame('', $svg);
        $this->assertStringNotContainsString('<script', $svg);

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($svg);
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, 'docs/pet.svg 必须是合法 XML');
        $this->assertSame('svg', $xml->getName());
    }
}
