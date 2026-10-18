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

    public function testFormsListsDefaultAndExpressions(): void
    {
        $forms = Mascot::forms();

        $this->assertCount(13, $forms);
        $this->assertArrayHasKey('default', $forms);
        $this->assertArrayHasKey('smile', $forms);
        $this->assertSame('笑', $forms['smile']['cn']);
        $this->assertSame('Smile', $forms['smile']['en']);
        $this->assertSame(':emoji-smile:', $forms['smile']['shortcode']);
        $this->assertNull($forms['default']['shortcode']);
    }

    public function testEveryFormIsWellFormedAndInert(): void
    {
        foreach (array_keys(Mascot::forms()) as $name) {
            $svg = Mascot::form($name);

            $this->assertNotSame('', $svg, "form $name");
            $this->assertStringNotContainsString('<script', $svg, "form $name");

            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($svg);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $this->assertNotFalse($xml, "form $name must be valid XML");
            $this->assertSame('svg', $xml->getName(), "form $name root");
        }
    }

    public function testSvgDefaultsToDefaultFormAndAcceptsNamedForm(): void
    {
        $this->assertSame(Mascot::form('default'), Mascot::svg());
        $this->assertSame(Mascot::form('smile'), Mascot::svg('smile'));
    }

    public function testFormNameIsNormalized(): void
    {
        $this->assertSame(Mascot::form('smile'), Mascot::form(' SMILE '));
    }

    public function testUnknownFormThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Mascot::form('unknown');
    }

    public function testPathTraversalFormNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Mascot::form('../pet');
    }
}
