# JWT Webman 插件单元测试报告

- 日期：2026-09-29
- 运行环境：PHP 8.5.6 / PHPUnit 9.6.35
- 运行命令：`vendor/bin/phpunit`

## 测试统计

| 指标 | 数值 |
|------|------|
| 测试总数 | 270 |
| 断言总数 | 518 |
| 通过 | 261 |
| 跳过 | 9（全部为 Memcached 扩展缺失） |
| 失败 | 0 |
| 错误 | 0 |

## 各模块覆盖清单

### 核心模块（src/erik-jwt/）
- [x] `Config.php` — 读取/嵌套设置/覆盖数组值/非数组覆盖报错/文件加载/文件缺失报错
- [x] `JWT.php` — 编码解码/自定义过期/标准声明(iss/aud/iat/nbf/exp/jti)/自定义 headers/刷新令牌/黑名单全流程/校验/leeway 容差/错误 issuer/audience/空 issuer/audience 兼容/保护声明(exp/jti)忽略/过期与失效令牌解码报错/无 jti 令牌黑名单返回 false/过期令牌黑名单返回 true/过期黑名单令牌 isBlacklisted 返回 false/存储故障默认 fail-closed 与 fail_open 放行对比/refresh 沿用配置 refresh_expire/requestToken 读取 HTTP_AUTHORIZATION 与 REDIRECT_HTTP_AUTHORIZATION/刷新令牌默认被拒（decode 与 validate）/算法拼写错误构造即报 CONFIG_ERROR/伪造非字符串 jti 不产生致命错误且黑名单查询返回 false/bearerToken 容错多余空白
- [x] `JwtWrapper.php` — 包装层/verify 显式令牌与自动读取请求头/无令牌报错
- [x] `JWTFactory.php` — 配置创建/密钥长度与空密钥校验/redis 需 resolver/数据库需 PDO/memcached 缺扩展报 STORAGE_ERROR/redis 前缀/自定义表名/重试包装(RetryTokenStorage)/自定义文件路径/自动清理/配置文件加载 createFromFile 与文件缺失报错/随包分发的原生配置模板可直接加载
- [x] `Native/Guard.php` — 显式令牌校验/请求头自动取令牌/无令牌抛 TOKEN_INVALID/check 不抛异常/requireAuth 返回 payload/401 响应体与框架中间件一致/黑名单令牌拒绝/由配置文件构建
- [x] `Mascot.php` — 横幅纯文本无色码/着色与纯文本内容一致/pet.svg 为合法 XML 且无脚本
- [x] `JWTException.php` — 异常码与消息

### 存储层
- [x] `FileTokenStorage.php` — 黑名单写入/查询/过期清理/GC 概率/统计/非十六进制 jti 转十六进制文件名且不逃出存储目录
- [x] `RedisTokenStorage.php` — ping 兼容(PONG/bool/+PONG)/exists 返回值兼容(setex 失败/异常包装)/前缀/重连成功与失败/过期不入库/`exists()` 返回 false 时抛错而非放行/非十六进制 jti 取 sha256 作为键
- [x] `DatabaseTokenStorage.php` — SQLite 黑名单/更新回退/清理/表名校验/自定义表名/建表失败与操作失败→STORAGE_ERROR/PDO 静默错误模式下重复主键回退更新/查询失败抛错而非放行/关闭自动建表
- [x] `MemcachedTokenStorage.php` — 扩展缺失时整体跳过（9 个跳过均由此产生）/非十六进制 jti 取 sha256 作为键
- [x] `RetryTokenStorage.php` — 重试包装

### 框架集成（纯 PHPUnit 桩，不依赖真实框架）
- [x] Laravel — Facade/中间件(放行/401/黑名单/过期)/ServiceProvider(合并配置、redis 存储、console 发布与注册命令)/InstallCommand(发布配置、写 .env、覆盖密钥、无 .env 不崩溃)
- [x] Webman — 中间件(except 放行/无 token/有效/无效/黑名单)
- [x] ThinkPHP — Facade/JWTService(redis、database 存储)/中间件/InstallCommand(受保护方法经反射调用)
- [x] Hyperf — ConfigProvider(依赖、命令注册、发布、属性)/中间件/JWTAspect/InstallCommand
- [x] Yii2 — JwtService(回退 params、注入 config 优先、密钥缺失报错、刷新轮换与拉黑、黑名单)/JwtIdentity(sub/uid/id 取值、jti 作 authKey、空 authKey 拒绝、findIdentity 无状态)/JwtAuth(无令牌、畸形、过期、刷新令牌当访问令牌、黑名单、有效令牌 payload 随身份对象返回并登录 user、Request 拒绝动态属性、WWW-Authenticate、optional 放行/非 optional 拒绝/optional 仍解析、组件类型错误)/InstallController(发布配置、已存在不覆盖、无 .env 回落打印)
- [x] Yii3 — PSR-15 中间件(except 放行、带/不带前导斜杠匹配、未命中仍需令牌、无令牌、无效、过期、刷新令牌当访问令牌、黑名单、有效令牌写入 `jwt_payload` 属性、原请求不被改写、非法 except 正则不影响请求)/InstallCommand(命令名取自 `#[AsCommand]`、写 .env、覆盖既有密钥不追加、无 .env 回落打印、params-console 注册映射)
- [x] `Install.php` — 常量/拷贝配置/幂等/卸载/未安装卸载不抛错

### 测试基础设施
- `tests/FrameworkStubs.php`（入口）拆分为 `tests/stubs/` 下 8 个文件（PSR/全局/Laravel/ThinkPHP/Webman/Hyperf/Yii2/Yii3），均 ≤500 行；所有类/接口/函数带 class_exists/function_exists 守卫，真实包存在时自动跳过桩定义
- Yii2 桩按真实源码复刻 `AuthMethod::beforeAction` 的流程（捕获 `UnauthorizedHttpException` → optional 放行 → 否则 challenge + handleFailure），因此 `optional` / 401 两条分支走的是真实语义而非简化替身

## 本轮变更：Yii2 / Yii3 适配（新增 41 个用例）

| 文件 | 行数 | 职责 |
|------|------|------|
| `Yii2/JwtService.php` | 118 | `yii\base\Component`，懒加载内核；按 `storage.type` 才取 `db`/`redis`/`memcached` 组件 |
| `Yii2/JwtAuth.php` | 125 | `yii\filters\auth\AuthMethod` 子类，挂 `behaviors()` 即可保护控制器 |
| `Yii2/JwtIdentity.php` | 67 | `yii\web\IdentityInterface`，让 `Yii::$app->user` 照常可用 |
| `Yii2/InstallController.php` | 59 | `php yii jwt/install` |
| `Yii2/config/jwt.php` | 57 | `getenv()` 版配置模板（无 `middleware` 段） |
| `Yii3/Middleware.php` | 92 | PSR-15 中间件，`except` 复用 `MiddlewareSupport` |
| `Yii3/InstallCommand.php` | 65 | `./yii jwt:install`（Symfony Console） |
| `Yii3/config/{params,di,params-console}.php` | 155 | config-plugin 三组，装包即生效 |

API 均按 `yiisoft/yii2` 与 `yiisoft/yii-console` 真实源码核对后实现，未凭记忆书写。关键依据：

- Yii2 `AuthMethod::beforeAction` **不调用** `login()`（登录发生在 `authenticate()` 内部，见 `HttpHeaderAuth`）；且它会捕获 `UnauthorizedHttpException`，因此"令牌缺失也直接抛异常"不会破坏 `optional` 语义
- `JwtAuth` 的登录写法照抄 Yii2 自带的 `HttpBasicAuth`：`if ($user->getIdentity(false) !== $identity) { $user->login($identity); }`
- Yii3 的 `config-plugin` 组名（`params` / `common` / `params-console`）取自 `yiisoft/config` 与 `yiisoft/yii-console` 的实际约定

### 本轮自查发现并修复

1. **Yii2 适配层给 `$request` 挂动态属性会导致每个请求 500（高危，已修复）**

   `yii\base\Request` 继承自 `yii\base\Component`，而 `Component::__set()` 对未声明属性**直接抛 `UnknownPropertyException`**（不是 PHP 8.2 那种动态属性弃用警告）。原实现照搬其余五个适配器的 `$request->jwt_payload = $payload;`，在真实 Yii2 下会让每一次认证成功的请求抛异常。

   现改为 payload 只挂在身份对象上（`Yii::$app->user->identity->payload`）——`JwtIdentity::$payload` 是声明过的公开属性，本就承载这个数据，也更贴合 Yii2 习惯。

2. **测试桩掩盖了上述缺陷（已修复）**

   原 `tests/stubs/YiiStubs.php` 给 `BaseObject`/`Component`/`Request` 加了 `#[\AllowDynamicProperties]`、且没有实现 `__get`/`__set`，与真实 Yii2 语义相反，导致动态属性赋值在桩里静默通过。现已按真实源码复刻：`BaseObject`/`Component` 提供会抛 `UnknownPropertyException` / `InvalidCallException` 的魔术方法，`Request` / `Response` / `User` 改为继承 `Component`。

   并新增钉子用例 `testRequestRejectsDynamicProperties`，把"不许往 Request 挂属性"这条约束锁死；已验证把 `$request->jwt_payload = $payload;` 加回去会立刻以 `Setting unknown property: yii\web\Request::jwt_payload` 失败。

## 修复的问题

14. **Redis 读路径 fail-open（高危）**：`isBlacklisted()` 里 `(bool) $redis->exists()` 在 phpredis 超时/链路错误返回 `false` 时与"0 个键"无法区分，会把已拉黑的令牌放行，而且不抛异常就绕过了 `storage.fail_open` 的 fail-closed 策略。改为 `false` 即抛 STORAGE_ERROR、`> 0` 才算拉黑（与 `blacklist()`、Memcached 的写法对齐）
15. **伪造 jti 导致的未认证 500**：`isBlacklisted()` 按设计不验签，payload 里 `{"jti":[...]}` 会让 storage 的 `string $jti` 抛 `TypeError`（`Error`，原先的两个 `catch` 都接不住）。在 `getPayloadWithoutValidation()` 丢弃非字符串 jti，并把捕获放宽到 `\Throwable`
16. **非十六进制 jti 各驱动行为不一致**：file / redis / memcached 直接抛 "Invalid JTI format"（`fail_open=false` 时合法令牌被 401，`true` 时该令牌的黑名单永不生效），database 却正常。改为统一归一化：file 用 `bin2hex`、redis/memcached 用 `sha256`，已有十六进制 jti 的键名不变，路径穿越同时被消除
17. **刷新令牌可当访问令牌用（高危）**：`decode()` 不校验 `token_type`，`Bearer <refresh token>` 能直接访问受保护接口，且刷新令牌有效期更长。`decode()` / `validate()` 默认拒绝 `token_type=refresh`，需要时传 `$allowRefresh = true`；`refresh()` / `blacklist()` 内部已放开
18. **`exp` 缺失时的 TypeError**：firebase v7 允许不带 `exp` 的令牌，`blacklist()` / `refresh()` 直接把 `$payload['exp']` 当 int 用会抛 `TypeError`（`refresh()` 还会在换发新令牌前中断）。统一取 `(int) ($payload['exp'] ?? 0)`，仅在 `exp > time()` 时写入黑名单
19. **memcached 默认 servers 被空数组覆盖**：默认合并里的 `'servers' => []` 让 `?? [['127.0.0.1', 11211]]` 永不生效 → `addServers([])` 后所有操作失败。改用 `?:`
20. **提前取数据库连接**：Laravel 的 `DB::connection()->getPdo()` 与 webman 的 `\support\Db::...` 在数组字面量里被立即求值，用 file/redis 存储的应用也会因为没装 webman/database 或数据库故障而整体不可用。改为与 ThinkPHP / Hyperf 一致，按 `storage.type` 取连接
21. **`auto_cleanup` 节流失效**：闭包内的 `static $lastCleanup` 在 PHP-FPM 下每个请求都会重置，`cleanup_interval` 形同虚设（每请求清理一次）。改用时间戳文件跨请求节流，并抽出 `cleanupDue()` 做单元测试
22. **算法未校验**：`JWT_ALGORITHM=hs256` 这类拼写错误能构造成功，之后 `encode()` 抛原始 DomainException、`decode()` 把全部合法令牌判为无效。构造时用 `FirebaseJWT::$supported_algs` 校验，立即报 CONFIG_ERROR
23. **配置与文档债务**：删除无人读取的 `storage.database`（README 曾把它写成"Redis 数据库编号"）；统一 webman / 原生模板的自动清理环境变量名（原为 `JWT_ADVANCED_AUTO_CLEANUP`，与其余三个框架及文档不一致）；README 补上 `servers` / `options`；删除与 Laravel 逐字节相同的 `ThinkPHP/helpers.php`（两者都在 autoload.files 里，后者永远是死代码）
24. **低危修复**：`bearerToken()` 容忍多余空白；`writeEnvSecret()` 返回成功与否、安装命令在 `.env` 缺失时明确提示而不再假装成功，并转义替换串中的反斜杠；`FileTokenStorage::cleanup()` 回收崩溃残留的 `*.json.tmp.*`；`gc_probability < 0.01` 由"永不触发"改为按概率正常触发；`expire_time` 由 `INT` 改 `BIGINT`

9. **原生 PHP 支持**：新增 `Native\Guard` 请求守卫与 `Native/config/jwt.php` 无框架配置模板；`JWTFactory::createFromFile()` 直接加载 PHP 配置文件；`JWT::requestToken()` 统一头部提取（含 `REDIRECT_HTTP_AUTHORIZATION`），`JwtWrapper::verify()` 可省略令牌参数
10. **`DatabaseTokenStorage` 静默失败**：PDO 默认的 `ERRMODE_SILENT` 下 `execute()` 只返回 `false`，重复主键不会进入更新分支（黑名单写入丢失）、查询失败会被当成"未拉黑"而放行。改为显式判断 SQLSTATE（23000/23505 视为冲突）与失败抛出，并统一 `prepare()` 失败的处理
11. **`refresh()` 忽略配置**：过期时间硬编码 3600，`refresh_expire` 配置形同虚设。默认改为 0，与 `encode()` 一致地回落到配置值
12. **存储故障策略可配**：`storage.fail_open`（默认 false = fail-closed，行为与之前一致）允许在存储整体不可用时放行并记 error 日志
13. **文档与实现对齐**：README 原写「核心可在 PHP 7.4+ 使用」（composer 实际要求 >= 8.0）、配置注释「密钥至少 16 字符」（`firebase/php-jwt` v7 实际要求 32 字符）、功能图原写「存储故障不影响正常令牌校验」（实际为拒绝）

0. **源码 PHP 8 兼容（保持公开 API 不变）**：
   - `FileTokenStorage::__construct(string $storagePath = null)` → `?string $storagePath = null`（PHP 8.4 隐式可空参数弃用）
   - `Hyperf/InstallCommand` 缺失 `protected $container` 属性声明（PHP 8.2+ 动态属性弃用）
1. **JWTFactory/各框架集成**：未设置密钥时 JWTFactory 抛 CONFIG_ERROR（密钥 ≥32 字符）——测试中统一预设有效密钥（测试侧约束，非源码缺陷）
2. **测试桩 `JwtTestApp::getConfigPath()/getRootPath()`** 缺少尾部 `/`，导致 ThinkPHP InstallCommand 的配置文件与 .env 写错路径（桩修正，对齐真实 think 框架返回带分隔符的路径）
3. **缺少 Laravel `config_path()` 辅助函数桩**，Laravel ServiceProvider boot 在 console 模式报未定义函数（补充桩）
4. **中间件 `$next` 返回类型**：ThinkPHP/Webman 中间件声明返回 `think\Response` / `Webman\Http\Response`，测试闭包原先返回字符串导致 TypeError（测试修正为返回桩 Response 对象）
5. **桩中 7 个 PSR/Hyperf 具体测试替身（JwtTestContainer/JwtTestPsr*/JwtTestHyperf*）** 被 `if (!interface_exists(...))` 守卫包裹，而桩接口总是先定义，导致这些类从未被声明——移除守卫使其无条件定义（原 FrameworkStubs.php 潜在缺陷）
6. **PHP 8.1+ 兼容**：PDO 匿名子类覆写 `exec()` 返回值类型 `int|false` 不匹配（`true` → `0`）
7. **PHP 8.2+ 动态属性**：各框架 Request 桩类使用 `#[\AllowDynamicProperties]`
8. **`tests/FrameworkStubs.php` 1229 行**超过项目 500 行上限——按命名空间拆分到 `tests/stubs/`（符号集合与原文件逐项比对一致）

## 遗留说明

- Memcached 相关 9 个测试因扩展未安装跳过（`MemcachedTokenStorageTest` 的 setUp 检测 `class_exists('Memcached')`）
- 框架集成测试基于桩类，未覆盖真实框架行为差异；若后续安装真实框架包，`tests/stubs/` 守卫会自动失效桩定义，但测试断言仍以桩语义为准
- `JWT::decode()` 的 `FirebaseJWT::$leeway` 为全局静态属性，多实例不同 leeway 会互相覆盖（源码注释已说明，测试按单实例使用）
- 过期令牌的 `isBlacklisted()` 返回 false（报告"已过期"而非"已拉黑"），`testIsBlacklistedExpiredBlacklistedTokenReturnsFalse` 固化该行为
- `Native\Guard::requireAuth()` 的失败分支会结束进程，单元测试只覆盖成功分支与 `Guard::respond()` 的输出，未做进程级退出测试；`examples/usage.php` 覆盖了 `authenticate()/check()` 的正常路径
- `Native\Guard` 的请求头提取在 CLI 下无法覆盖 `getallheaders()` 路径（CLI SAPI 不提供该函数），该分支依赖真实 Web SAPI
- Yii2 的 401 是**抛 `yii\web\UnauthorizedHttpException`**、交给 Yii 错误处理器渲染，而其余五个适配器自行拼装 `{code,msg,data}` JSON。这是刻意的框架惯用写法差异，已在 README 中标注
- Yii2 的 `except` / `only` / `optional` 由框架 `ActionFilter` 提供，非本包代码，故桩与测试只覆盖 `beforeAction` 的 optional / 401 分支与 `authenticate()` 本身
- Yii3 的 `storage.connection` 需由应用显式指定容器服务 id（本包不硬编码 `yiisoft/db`、`yiisoft/redis` 的接口名，避免跟随其版本漂移）；未配置时工厂抛带说明的 STORAGE_ERROR，该分支未写用例（需真实容器）
- `docs/review-report-20260802.md` 为 2026-08-02 的历史审查记录，未随本轮改动更新
- 插图中 `docs/pet.svg` 已重绘为六齿（刃身相应加长 32px，脚与投影随之下移，viewBox 240×310）；`docs/architecture.svg` 接入层已扩为 7 个方块
