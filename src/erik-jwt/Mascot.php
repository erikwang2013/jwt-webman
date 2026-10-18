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
 * 项目宠物「钥匙小卫 Kee」——一只扁平卡通风格、坐在门口、挂着钥匙吊牌的柴犬门卫。
 *
 * 造型取意：柴犬本体 = 项目本身；项圈上的六颗金色圆钉 = webman / Laravel /
 * ThinkPHP / Hyperf / Yii2 / Yii3 六个框架适配层；没有第七颗圆钉 = 原生 PHP
 * 直接使用内核；金色钥匙吊牌 = 框架无关内核与 JWT 令牌；钥匙柄上的蓝色盾牌
 * = 校验与黑名单。
 * 矢量形象见 docs/pet.svg，12 种表情形态见 docs/pet-expressions/；
 * 均可通过 Mascot::form() / Mascot::forms() 在代码中取用。
 */
final class Mascot
{
    /** 英文名 */
    public const NAME = 'Kee';

    /** 中文名 */
    public const CN_NAME = '钥匙小卫';

    /** 项目主页 */
    public const HOME = 'https://github.com/erikwang2013/jwt-webman';

    /**
     * 全部形态：default 为默认坐姿，其余为 12 种表情。
     *
     * @var array<string, array{cn:string, en:string, shortcode:?string}>
     */
    private const FORMS = [
        'default'    => ['cn' => '默认',   'en' => 'Default',     'shortcode' => null],
        'smile'      => ['cn' => '笑',     'en' => 'Smile',       'shortcode' => ':emoji-smile:'],
        'joy'        => ['cn' => '欢乐',   'en' => 'Joy',         'shortcode' => ':emoji-joy:'],
        'cry'        => ['cn' => '哭',     'en' => 'Cry',         'shortcode' => ':emoji-cry:'],
        'depressed'  => ['cn' => '沮丧',   'en' => 'Depressed',   'shortcode' => ':emoji-depressed:'],
        'terrified'  => ['cn' => '惊恐',   'en' => 'Terrified',   'shortcode' => ':emoji-terrified:'],
        'angry'      => ['cn' => '愤怒',   'en' => 'Angry',       'shortcode' => ':emoji-angry:'],
        'coquettish' => ['cn' => '撒娇',   'en' => 'Coquettish',  'shortcode' => ':emoji-coquettish:'],
        'cute'       => ['cn' => '卖萌',   'en' => 'Cute',        'shortcode' => ':emoji-cute:'],
        'pleasing'   => ['cn' => '讨好',   'en' => 'Pleasing',    'shortcode' => ':emoji-pleasing:'],
        'knocking'   => ['cn' => '敲打',   'en' => 'Knocking',    'shortcode' => ':emoji-knocking:'],
        'facepalm'   => ['cn' => '捂脸',   'en' => 'Facepalm',    'shortcode' => ':emoji-facepalm:'],
        'tongue'     => ['cn' => '吐舌头', 'en' => 'Tongue Out',  'shortcode' => ':emoji-tongue:'],
    ];

    private const ART = <<<'ART'
        /\            /\
       /  \__________/  \
      /  o            o  \
     |         ^          |
     |        / \         |
      \       \_/        /
       \________________/
              |###|
              |___|
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
            '  ' . self::paint(self::CN_NAME . ' ' . self::NAME . ' 已就位 —— 一只守门柴，替你看好每一枚令牌', $color, self::GOLD),
            '  ' . self::HOME,
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * 全部宠物形态：默认坐姿 + 12 种表情。
     *
     * @return array<string, array{cn:string, en:string, shortcode:?string}>
     */
    public static function forms(): array
    {
        return self::FORMS;
    }

    /**
     * 取指定形态的 SVG 源码。
     *
     * @param string $name 形态名（见 forms()），大小写与首尾空白不敏感
     * @throws \InvalidArgumentException 形态名不存在
     */
    public static function form(string $name): string
    {
        $name = strtolower(trim($name));

        if (!isset(self::FORMS[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown mascot form "%s"; available: %s',
                $name,
                implode(', ', array_keys(self::FORMS))
            ));
        }

        $root = dirname(__DIR__, 2);
        $path = $name === 'default'
            ? $root . '/docs/pet.svg'
            : $root . '/docs/pet-expressions/' . $name . '.svg';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * 宠物 SVG 源码，可直接输出到浏览器、写入文件或内联进 HTML。
     *
     * @param string|null $form 形态名；null 取默认坐姿形态
     */
    public static function svg(?string $form = null): string
    {
        return self::form($form ?? 'default');
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
