# JWT-Webman 代码审查报告

**日期**: 2026-08-02  
**项目**: erikwang2013/jwt-webman  
**测试**: 75 tests, 120 assertions, 1 skipped (Memcached 扩展未安装) — 全部通过  
**修复状态**: 已修复 6 项，详见各条目

---

## 一、总体评价

代码质量良好。核心逻辑与框架适配层分离清晰，异常层级设计合理，存储驱动可插拔架构实现到位。测试覆盖了核心路径，全部通过。

以下是按优先级排列的问题与优化建议。

---

## 二、Bug / 潜在问题

### 2.1 [中] `JwtWrapper::currentToken()` 直接读取 `$_SERVER['HTTP_AUTHORIZATION']`

**位置**: `src/erik-jwt/JwtWrapper.php:58-64`

```php
private function currentToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    ...
}
```

**问题**: 在 Nginx + PHP-FPM 环境下，`HTTP_AUTHORIZATION` 默认不会传递到 `$_SERVER`。需要额外配置 `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` 才能工作。如果取不到，`refresh()` 会收到空字符串，后续 `decode('')` 抛出异常，用户看到的错误信息不友好。

**建议**: 增加 `getallheaders()` 或 `apache_request_headers()` 作为 fallback，或在返回空字符串时抛出明确的异常。  
**✅ 已修复**: 增加 `getallheaders()` 回退，无 Token 时抛出明确异常。

### 2.2 [低] `validate()` 将验证失败记录为 `error` 级别日志

**位置**: `src/erik-jwt/JWT.php:106-114`

```php
public function validate(string $token): bool
{
    try {
        $this->decode($token);
        return true;
    } catch (Exception $e) {
        $this->logger->error($e->getMessage());  // ← 验证失败是正常流程
        return false;
    }
}
```

**问题**: `validate()` 是一个"检查是否有效"的方法，返回 false 是正常结果。将正常的验证失败记录为 `error` 会污染日志，干扰告警。

**建议**: 改为 `logger->warning()` 或 `logger->info()`，或直接不记录（让调用方决定是否记录）。  
**✅ 已修复**: 改为 `logger->warning()`。

### 2.3 [中] `register_shutdown_function` 不适合常驻进程

**位置**: `src/erik-jwt/JWTFactory.php:147-159`

```php
register_shutdown_function(function () use ($jwt, $cleanupInterval) {
    ...
});
```

**问题**: 在 webman（基于 Workerman）和 Hyperf（基于 Swoole/Swow）的常驻内存进程中，`register_shutdown_function` 只在 worker 进程退出时触发，不会在每个请求结束时触发。这意味着：
- 清理几乎不会执行
- 所有实例共享同一个 `static $registered` 和 `static $lastCleanup`，第一个实例注册后，后续创建的实例不会再注册

**建议**: 对于 webman/Hyperf，使用框架自身的定时任务机制；或者在请求中间件的 `process()` 方法中触发概率性清理。  
**✅ 已修复**: README 中增加常驻进程模式下的定时清理示例（Webman Cron / Hyperf Crontab）。

---

## 三、安全隐患

### 3.1 [中] `FileTokenStorage::unlinkAsync()` 使用 `exec()`

**位置**: `src/erik-jwt/FileTokenStorage.php:144-153`

```php
private function unlinkAsync(string $filePath): void
{
    if (function_exists('exec') && stripos(PHP_OS, 'WIN') !== 0) {
        exec("rm -f " . escapeshellarg($filePath) . " > /dev/null 2>&1 &");
    } else {
        @unlink($filePath);
    }
}
```

**问题**:
1. `exec()` 在许多生产环境中被 `disabled_functions` 禁用，此时退化为同步 `unlink()`
2. 异步 `rm -f` 在极端情况下可能删除正在被其他进程读取的文件
3. `@unlink` 静默失败，文件泄漏无从发现

**建议**: 既然已经做了 GC 概率性清理，过期文件异步删除的收益不大。直接改为同步 `unlink()`，失败时记录 warning。  
**✅ 已修复**: 移除 `exec()`，改为同步 `unlink()` + `error_log`。

### 3.2 [低] `blacklist()` 两次 catch 块都吞掉异常

**位置**: `src/erik-jwt/JWT.php:155-176`

```php
} catch (JWTException $e) {
    ...
    try {
        $payload = $this->getPayloadWithoutValidation($token);
        ...
    } catch (Exception $e) {
        $this->logger->error($e->getMessage());
        // 忽略解析错误
    }
    ...
}
```

**问题**: 内层 `getPayloadWithoutValidation()` 如果因 token 格式正确但内容异常抛错，异常被吞掉，调用方只能看到 `blacklist()` 返回 `false`，无法区分"格式错误"和"存储故障"。

**建议**: 区分异常类型，`JWTException`（格式错误）可以静默返回 false，但存储级异常应继续向上抛出。  
**✅ 已修复**: 内层 catch 仅捕获 `JWTException`，`RuntimeException` 等存储异常不再被吞掉。

---

## 四、代码质量 / 架构优化

### 4.1 [低] `FirebaseJWT::$leeway` 静态属性竞态

**位置**: `src/erik-jwt/JWT.php:83`

代码中已有注释说明此问题（77-79 行），属于底层库的限制。对于绝大多数使用场景影响不大，但值得在 README 中注明：同一进程中不应存在不同 leeway 配置的 JWT 实例。

### 4.2 [低] `RedisTokenStorage` 连接检查逻辑冗余

**位置**: `src/erik-jwt/RedisTokenStorage.php:46-62`  
**✅ 已修复**: 简化重连逻辑，移除冗余的 `$connectionChecked = false` 重置。

### 4.3 [低] `FileTokenStorage` GC 调用频率

**位置**: `src/erik-jwt/FileTokenStorage.php:60`

每次 `blacklist()` 都有一轮 GC 概率判断（默认 10%）。在高并发写入场景下会频繁触发 `glob()` + 逐个 `file_get_contents()` + `json_decode()`，造成 IO 抖动。

**建议**: 增加最小 GC 间隔（如至少 60 秒才允许触发下一次 GC），避免高频执行。

### 4.4 [信息] `encode()` 中 `array_merge` 行为

**位置**: `src/erik-jwt/JWT.php:69`

```php
$finalPayload = array_merge($defaultPayload, $payload);
```

用户 payload 的 `iat`、`exp` 等字段会覆盖默认值。这是有意为之（允许用户指定过期时间），但文档中未明确说明哪些字段可被覆盖、哪些不应被覆盖（如 `jti`）。

---

## 五、测试覆盖缺失

| 模块 | 状态 |
|------|------|
| JWT 核心 | 已覆盖 |
| JWTFactory | 已覆盖 |
| Config | 已覆盖 ✅ 新增 fromFile 测试 |
| FileTokenStorage | 已覆盖 |
| JWTException | 已覆盖 |
| **JwtWrapper** | **✅ 已补充（12 tests）** |
| **RetryTokenStorage** | **✅ 已补充（6 tests）** |
| RedisTokenStorage | 无测试（需 Redis） |
| DatabaseTokenStorage | 无测试（需 DB） |
| RS256 算法 | 无测试 |
| leeway > 0 行为 | 无测试 |
| 各框架中间件 | 无集成测试 |

### 5.1 建议优先补充

1. **JwtWrapper 测试** — 这是对外推荐的主入口，零测试是最大的风险敞口
2. **RetryTokenStorage 测试** — 模拟失败→重试→成功的场景
3. **Config::fromFile() 测试** — 文件不存在、文件返回非数组等边界条件
4. **leeway 测试** — 验证时钟偏差容忍行为

---

## 六、其他建议

### 6.1 `composer.json`

- `minimum-stability: "dev"` — 发布到 Packagist 时建议改为 `"stable"`，避免用户安装到不稳定版本的依赖
- 缺少 `conflict` 声明 — 已知 `firebase/php-jwt` v7 的 `$leeway` 行为，如果有不兼容版本应声明

### 6.2 缺少静态分析

建议在 CI 中加入 PHPStan 或 Psalm（至少 level 5），能发现潜在的类型问题。当前代码用 `declare(strict_types=1)` 但方法签名缺少返回类型声明（PHP 7.4 已支持）。

### 6.3 刷新令牌一次性使用

当前 `refresh()` 会黑名单旧 token，但新 token 可以多次使用。如果需要更强的安全性（refresh token rotation），应在刷新时也标记旧 refresh token 一次性使用。

---

## 七、总结

| 类别 | 数量 |
|------|------|
| Bug / 潜在问题 | 3 |
| 安全隐患 | 2 |
| 代码质量优化 | 4 |
| 测试覆盖缺失 | 8 模块/场景 |
| 其他建议 | 3 |

**整体健康度: 良好**。核心逻辑健壮，测试全部通过。主要改进方向：
1. 补充 JwtWrapper 和 RetryTokenStorage 的单元测试
2. 修复 `currentToken()` 的兼容性问题
3. 调整 `validate()` 的日志级别
4. 替换 `exec()` 为同步删除
