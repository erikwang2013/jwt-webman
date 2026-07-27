<?php
declare(strict_types=1);
namespace Erikwang2013\Jwt\Tests;
use Erikwang2013\Jwt\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testGetTopLevel(): void
    {
        $config = new Config(['key' => 'value']);
        $this->assertSame('value', $config->get('key'));
    }
    public function testGetNested(): void
    {
        $config = new Config(['a' => ['b' => ['c' => 'd']]]);
        $this->assertSame('d', $config->get('a.b.c'));
    }
    public function testGetDefaultWhenNotFound(): void
    {
        $config = new Config([]);
        $this->assertNull($config->get('missing'));
        $this->assertSame('default', $config->get('missing', 'default'));
    }
    public function testSetTopLevel(): void
    {
        $config = new Config([]);
        $config->set('key', 'value');
        $this->assertSame('value', $config->get('key'));
    }
    public function testSetNested(): void
    {
        $config = new Config([]);
        $config->set('a.b.c', 'value');
        $this->assertSame('value', $config->get('a.b.c'));
    }
    public function testSetNestedCreatesIntermediateArrays(): void
    {
        $config = new Config([]);
        $config->set('a.b.c', 42);
        $this->assertSame(42, $config->get('a.b.c'));
        $this->assertIsArray($config->get('a'));
        $this->assertIsArray($config->get('a.b'));
    }
    public function testSetOverwriteNestedNonArrayThrows(): void
    {
        $config = new Config(['a' => 'string']);
        $this->expectException(\Erikwang2013\Jwt\JWTException::class);
        $config->set('a.b', 'value');
    }
    public function testToArray(): void
    {
        $data = ['foo' => 'bar'];
        $config = new Config($data);
        $this->assertSame($data, $config->toArray());
    }
    public function testGetPartialNestedPathReturnsDefault(): void
    {
        $config = new Config(['a' => ['b' => 'c']]);
        $this->assertSame('default', $config->get('a.b.c.d', 'default'));
    }
}
