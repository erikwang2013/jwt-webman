<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Yii2;

use yii\base\BaseObject;
use yii\web\IdentityInterface;

/**
 * 把已校验的 JWT payload 包装成 Yii2 身份对象。
 *
 * 目的是让 JwtAuth 认证通过后 Yii::$app->user->identity / isGuest / id 照常可用。
 * 令牌是无状态的，没有可按 id 回查的用户表，因此两个 findIdentity*() 一律返回 null
 * —— 身份完全来自令牌本身，这也正是"只信令牌、不查库"该有的行为。
 */
class JwtIdentity extends BaseObject implements IdentityInterface
{
    /**
     * @var array 已通过签名与有效期校验的 JWT payload
     */
    public $payload = [];

    public static function findIdentity($id)
    {
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return null;
    }

    /**
     * 依次取 sub / uid / id 声明作为用户标识。
     */
    public function getId()
    {
        $id = $this->payload['sub'] ?? $this->payload['uid'] ?? $this->payload['id'] ?? null;

        return $id === null ? '' : (string) $id;
    }

    /**
     * 用 jti 作为认证键，使每次签发/刷新的令牌互不等价。
     */
    public function getAuthKey()
    {
        $jti = $this->payload['jti'] ?? '';

        return is_string($jti) ? $jti : '';
    }

    public function validateAuthKey($authKey)
    {
        $expected = $this->getAuthKey();

        return $expected !== '' && hash_equals($expected, (string) $authKey);
    }
}
