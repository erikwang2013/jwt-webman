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
use Erikwang2013\Jwt\JWTException;
use Yii;
use yii\base\InvalidConfigException;
use yii\filters\auth\AuthMethod;
use yii\web\UnauthorizedHttpException;

/**
 * Yii2 认证过滤器：挂到控制器或模块的 behaviors() 上即可保护该控制器的全部动作。
 *
 *     public function behaviors()
 *     {
 *         $behaviors = parent::behaviors();
 *         $behaviors['jwt'] = [
 *             'class'    => \Erikwang2013\Jwt\Yii2\JwtAuth::class,
 *             'except'   => ['login'],   // 原生 ActionFilter 属性：按 action id 排除
 *             'optional' => ['profile'], // 原生 AuthMethod 属性：公开动作，带令牌时仍解析
 *         ];
 *         return $behaviors;
 *     }
 *
 * 校验通过后，JwtIdentity 会登录进 Yii::$app->user，payload 就在身份对象上：
 *
 *     $payload = Yii::$app->user->identity->payload;
 *     $userId  = Yii::$app->user->id;
 *
 * 注意：不要往 $request 上挂自定义属性（如 $request->jwt_payload）—— yii\base\Request
 * 继承自 yii\base\Component，而 Component::__set() 对未声明属性直接抛
 * UnknownPropertyException，不是发个弃用警告了事。
 *
 * 令牌是无状态的，请在 user 组件上关掉会话，否则每个请求都会起一次 session：
 *
 *     'user' => ['identityClass' => ..., 'enableSession' => false, 'enableAutoLogin' => false],
 *
 * 认证失败抛 yii\web\UnauthorizedHttpException（401），交给 Yii 错误处理器渲染，
 * 因此响应体格式跟随 response->format —— 这是 Yii2 的原生做法，与另外五个适配器
 * 自行拼装 {code,msg,data} JSON 不同。
 */
class JwtAuth extends AuthMethod
{
    /**
     * @var string JWT 组件在应用中的 id
     */
    public $service = 'jwt';

    /**
     * @var string WWW-Authenticate 头里的 realm
     */
    public $realm = 'api';

    /**
     * @param \yii\web\User $user
     * @param \yii\web\Request $request
     * @param \yii\web\Response $response
     * @return \yii\web\IdentityInterface|null
     * @throws UnauthorizedHttpException 令牌缺失、无效、过期或在黑名单中
     */
    public function authenticate($user, $request, $response)
    {
        $token = JWT::bearerToken($request->getHeaders()->get('Authorization', ''));

        if ($token === '') {
            $this->fail($response, 'Token not provided');
        }

        try {
            $payload = $this->getService()->decode($token);
        } catch (JWTException $e) {
            $this->fail($response, JWTException::userMessage($e));
        }

        $identity = new JwtIdentity(['payload' => $payload]);

        // 与 Yii2 自带的 HttpBasicAuth 一致：已经登录成同一个身份时不再重复登录
        if ($user->getIdentity(false) !== $identity) {
            $user->login($identity);
        }

        return $identity;
    }

    public function challenge($response)
    {
        $response->getHeaders()->set('WWW-Authenticate', "Bearer realm=\"{$this->realm}\"");
    }

    /**
     * 先补上 WWW-Authenticate 再抛 401。
     *
     * AuthMethod::beforeAction 会捕获这个异常，optional 列表里的动作照常放行，
     * 所以令牌缺失也走同一条路径，不必为公开动作单独开分支。
     */
    private function fail($response, string $message): void
    {
        $this->challenge($response);

        throw new UnauthorizedHttpException($message);
    }

    private function getService(): JwtService
    {
        $service = Yii::$app->get($this->service);

        if (!$service instanceof JwtService) {
            throw new InvalidConfigException(
                "The '{$this->service}' component must be an instance of " . JwtService::class . '.'
            );
        }

        return $service;
    }
}
