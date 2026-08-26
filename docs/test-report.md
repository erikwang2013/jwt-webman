# JWT Webman 插件单元测试报告

- 日期：2026-08-27
- 运行环境：PHP 8.3.7 / PHPUnit 9.6.35
- 运行命令：`vendor/bin/phpunit`

## 测试统计

| 指标 | 数值 |
|------|------|
| 测试总数 | 202 |
| 断言总数 | 371 |
| 通过 | 192 |
| 跳过 | 10（全部为 Memcached 扩展缺失） |
| 失败 | 0 |
| 错误 | 0 |

## 各模块覆盖清单

### 核心模块（src/erik-jwt/）
- [x] `Config.php` — 读取/嵌套设置/覆盖数组值/非数组覆盖报错/文件加载/文件缺失报错
- [x] `JWT.php` — 编码解码/自定义过期/标准声明(iss/aud/iat/nbf/exp/jti)/自定义 headers/刷新令牌/黑名单全流程/校验/leeway 容差/错误 issuer/audience/空 issuer/audience 兼容/保护声明(exp/jti)忽略/过期与失效令牌解码报错/无 jti 令牌黑名单返回 false/过期令牌黑名单返回 true/过期黑名单令牌 isBlacklisted 返回 false
- [x] `JwtWrapper.php` — 包装层
- [x] `JWTFactory.php` — 配置创建/密钥长度与空密钥校验/redis 需 resolver/数据库需 PDO/memcached 缺扩展报 STORAGE_ERROR/redis 前缀/自定义表名/重试包装(RetryTokenStorage)/自定义文件路径/自动清理
- [x] `JWTException.php` — 异常码与消息

### 存储层
- [x] `FileTokenStorage.php` — 黑名单写入/查询/过期清理/GC 概率/统计
- [x] `RedisTokenStorage.php` — ping 兼容(PONG/bool/+PONG)/exists 返回值兼容(setex 失败/异常包装)/前缀/重连成功与失败/过期不入库/jti 校验
- [x] `DatabaseTokenStorage.php` — SQLite 黑名单/更新回退/清理/表名校验/自定义表名/建表失败与操作失败→STORAGE_ERROR
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
