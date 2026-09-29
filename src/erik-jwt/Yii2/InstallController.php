<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Yii2;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\Mascot;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * 安装命令：php yii jwt/install
 *
 * 在 config/console.php 里注册控制器映射（Yii2 默认的 controllerNamespace
 * 不包含本包，必须显式映射）：
 *
 *     'controllerMap' => [
 *         'jwt' => \Erikwang2013\Jwt\Yii2\InstallController::class,
 *     ],
 */
class InstallController extends Controller
{
    public function actionIndex(): int
    {
        $this->stdout(Mascot::banner());

        $dest = Yii::getAlias('@app') . '/config/jwt.php';
        if (file_exists($dest)) {
            $this->stdout("Config already exists at: {$dest}\n", Console::FG_YELLOW);
        } else {
            copy(__DIR__ . '/config/jwt.php', $dest);
            $this->stdout("Config published to: {$dest}\n", Console::FG_GREEN);
        }

        $secretKey = bin2hex(random_bytes(32));

        if (!JWT::writeEnvSecret(Yii::getAlias('@app') . '/.env', 'JWT_SECRET_KEY', $secretKey)) {
            $this->stdout(".env not found or not writable — please add this line manually:\n", Console::FG_YELLOW);
            $this->stdout("JWT_SECRET_KEY={$secretKey}\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        $this->stdout("JWT plugin installed successfully!\n", Console::FG_GREEN);
        $this->stdout("JWT_SECRET_KEY: {$secretKey}\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
