# JWT Webman 插件单元测试报告

- 日期：2026-09-25
- 运行环境：PHP 8.3.7 / PHPUnit 9.6.35
- 运行命令：`vendor/bin/phpunit`

## 测试统计

| 指标 | 数值 |
|------|------|
| 测试总数 | 225 |
| 断言总数 | 416 |
| 通过 | 215 |
| 跳过 | 10（全部为 Memcached 扩展缺失） |
| 失败 | 0 |
| 错误 | 0 |

## 各模块覆盖清单

### 核心模块（src/erik-jwt/）
- [x] `Config.php` — 读取/嵌套设置/覆盖数组值/非数组覆盖报错/文件加载/文件缺失报错
- [x] `JWT.php` — 编码解码/自定义过期/标准声明(iss/aud/iat/nbf/exp/jti)/自定义 headers/刷新令牌/黑名单全流程/校验/leeway 容差/错误 issuer/audience/空 issuer/audience 兼容/保护声明(exp/jti)忽略/过期与失效令牌解码报错/无 jti 令牌黑名单返回 false/过期令牌黑名单返回 true/过期黑名单令牌 isBlacklisted 返回 false/存储故障默认 fail-closed 与 fail_open 放行对比/refresh 沿用配置 refresh_expire/requestToken 读取 HTTP_AUTHORIZATION 与 REDIRECT_HTTP_AUTHORIZATION
- [x] `JwtWrapper.php` — 包装层/verify 显式令牌与自动读取请求头/无令牌报错
- [x] `JWTFactory.php` — 配置创建/密钥长度与空密钥校验/redis 需 resolver/数据库需 PDO/memcached 缺扩展报 STORAGE_ERROR/redis 前缀/自定义表名/重试包装(RetryTokenStorage)/自定义文件路径/自动清理/配置文件加载 createFromFile 与文件缺失报错/随包分发的原生配置模板可直接加载
- [x] `Native/Guard.php` — 显式令牌校验/请求头自动取令牌/无令牌抛 TOKEN_INVALID/check 不抛异常/requireAuth 返回 payload/401 响应体与框架中间件一致/黑名单令牌拒绝/由配置文件构建
- [x] `Mascot.php` — 横幅纯文本无色码/着色与纯文本内容一致/pet.svg 为合法 XML 且无脚本
- [x] `JWTException.php` — 异常码与消息

### 存储层
- [x] `FileTokenStorage.php` — 黑名单写入/查询/过期清理/GC 概率/统计
- [x] `RedisTokenStorage.php` — ping 兼容(PONG/bool/+PONG)/exists 返回值兼容(setex 失败/异常包装)/前缀/重连成功与失败/过期不入库/jti 校验
- [x] `DatabaseTokenStorage.php` — SQLite 黑名单/更新回退/清理/表名校验/自定义表名/建表失败与操作失败→STORAGE_ERROR/PDO 静默错误模式下重复主键回退更新/查询失败抛错而非放行/关闭自动建表
- [x] `MemcachedTokenStorage.php` — 扩展缺失时整体跳过（10 个跳过均由此产生）
- [x] `RetryTokenStorage.php` — 重试包装

### 框架集成（纯 PHPUnit 桩，不依赖真实框架）
- [x] Laravel — Facade/中间件(放行/401/黑名单/过期)/ServiceProvider(合并配置、redis 存储、console 发布与注册命令)/InstallCommand(发布配置、写 .env、覆盖密钥、无 .env 不崩溃)
- [x] Webman — 中间件(except 放行/无 token/有效/无效/黑名单)
- [x] ThinkPHP — Facade/JWTService(redis、database 存储)/中间件/InstallCommand(受保护方法经反射调用)
- [x] Hyperf — ConfigProvider(依赖、命令注册、发布、属性)/中间件/JWTAspect/InstallCommand
- [x] `Install.php` — 常量/拷贝配置/幂等/卸载/未安装卸载不抛错

### 测试基础设施
- `tests/FrameworkStubs.php`（入口）拆分为 `tests/stubs/` 下 6 个文件（PSR/全局/Laravel/ThinkPHP/Webman/Hyperf），均 ≤500 行；所有类/接口/函数带 class_exists/function_exists 守卫，真实包存在时自动跳过桩定义

## 修复的问题

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

- Memcached 相关 10 个测试因扩展未安装跳过（`MemcachedTokenStorageTest` 的 setUp 检测 `class_exists('Memcached')`）
- 框架集成测试基于桩类，未覆盖真实框架行为差异；若后续安装真实框架包，`tests/stubs/` 守卫会自动失效桩定义，但测试断言仍以桩语义为准
- `JWT::decode()` 的 `FirebaseJWT::$leeway` 为全局静态属性，多实例不同 leeway 会互相覆盖（源码注释已说明，测试按单实例使用）
- 过期令牌的 `isBlacklisted()` 返回 false（报告"已过期"而非"已拉黑"），`testIsBlacklistedExpiredBlacklistedTokenReturnsFalse` 固化该行为
- `Native\Guard::requireAuth()` 的失败分支会结束进程，单元测试只覆盖成功分支与 `Guard::respond()` 的输出，未做进程级退出测试；`examples/usage.php` 覆盖了 `authenticate()/check()` 的正常路径
- `Native\Guard` 的请求头提取在 CLI 下无法覆盖 `getallheaders()` 路径（CLI SAPI 不提供该函数），该分支依赖真实 Web SAPI
