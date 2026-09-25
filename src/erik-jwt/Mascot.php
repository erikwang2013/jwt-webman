<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt;

/**
 * 项目宠物「钥匙小卫 Kee」。
 *
 * 造型取意：钥匙柄（带表情的圆头）= 框架无关内核，钥匙刃右侧的四颗齿 =
 * webman / Laravel / ThinkPHP / Hyperf 四个框架适配层，胸前的盾牌 = 校验与黑名单。
 * 矢量形象见 docs/pet.svg，安装命令与项目文档共用同一形象。
 */
final class Mascot
{
    /** 英文名 */
    public const NAME = 'Kee';

    /** 中文名 */
    public const CN_NAME = '钥匙小卫';

    /** 项目主页 */
    public const HOME = 'https://github.com/erikwang2013/jwt-webman';

    private const ART = <<<'ART'
       .--------------.
      /   o        o   \
     |         ^        |
     |       \___/      |
      \        |       /
       '.      |     .'
         |     |     |
      .--+-----+-----+--.
     |      ( ### )      |
      '--+-----+-----+--'
         |     |     |
         |     |     +--.
         |     |     +--.
         |_____|_____|
ART;

    private const GOLD = "\033[38;5;214m";
    private const BLUE = "\033[38;5;39m";
    private const BOLD = "\033[1m";
    private const DIM  = "\033[2m";
    private const RESET = "\033[0m";

    /**
     * 终端横幅：安装命令与命令行工具使用。
     *
     * @param bool|null $color true 强制着色，false 强制纯文本，null 自动探测 TTY
     */
    public static function banner(?bool $color = null): string
    {
        $color = $color ?? self::supportsColor();

        $art = [];
        foreach (explode("\n", self::ART) as $line) {
            if (!$color) {
                $art[] = $line;
                continue;
            }
            $art[] = (strpos($line, '###') === false ? self::GOLD : self::BLUE) . $line . self::RESET;
        }

        $lines = [
            '',
            implode("\n", $art),
            '',
            '  ' . self::paint('erikwang2013/jwt-webman', $color, self::BOLD) . self::paint('  ·  PHP 多框架 JWT 认证插件', $color, self::DIM),
            '  ' . self::paint(self::CN_NAME . ' ' . self::NAME . ' 已就位 —— 一套核心 · 四框架通行', $color, self::GOLD),
            '  ' . self::HOME,
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * 宠物 SVG 源码，可直接输出到浏览器、写入文件或内联进 HTML。
     */
    public static function svg(): string
    {
        $path = dirname(__DIR__, 2) . '/docs/pet.svg';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private static function paint(string $text, bool $color, string $code): string
    {
        return $color ? $code . $text . self::RESET : $text;
    }

    private static function supportsColor(): bool
    {
        return PHP_SAPI === 'cli'
            && defined('STDOUT')
            && function_exists('stream_isatty')
            && @stream_isatty(STDOUT);
    }
}
