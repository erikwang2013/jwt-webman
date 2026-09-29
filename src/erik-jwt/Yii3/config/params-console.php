<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

/**
 * 把 jwt:install 注册进 yii-console。合并进应用的 params-console 组，
 * 应用无需额外配置即可执行 ./yii jwt:install。
 *
 * 键名即命令名（yii-console 的 CommandLoader 以配置里的名字为准）。
 */

use Erikwang2013\Jwt\Yii3\InstallCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'jwt:install' => InstallCommand::class,
        ],
    ],
];
