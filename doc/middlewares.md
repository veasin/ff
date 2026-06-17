
# 预制中间件

所有预制中间件返回 `callable`，直接传入 `middleware()` 或 `hump()` 使用：

```php
middleware(cors(), auth(), log(), $handler);
```

**认证相关**

## auth(prefix, realm) — HTTP Basic 认证

旧版 Basic 认证。从 `Authorization: Basic` 头提取用户名密码，调用验证器（验证器返回 bool）。

```php
container('#mw:auth:validators', [fn($user, $pass) => true]);//设置验证器
middleware(auth(), $handler);//使用中间件
container('#mw:auth:user');//获取认证用户
```

参数：
- **`$prefix`**: `string` 默认 `'#mw:auth'` 容器键前缀
- **`$realm`**: `?string` 默认 `null` WWW-Authenticate realm，null 时使用 i18n 翻译

容器配置：
- **`{prefix}:validators`**: `array` - 验证器数组，接收 `($user, $pass)` 返回 bool
- **`{prefix}:user`**: `mixed` - 认证通过后写入用户信息

---

## basic(prefix, realm) — HTTP Basic 认证（推荐）

新版 Basic 认证。验证器返回值直接存入 user（可返回用户对象而非 `true`）。

```php
container('#mw:auth:validators', [fn($user, $pass) => $user]);//设置验证器
middleware(basic(), $handler);//使用中间件
container('#mw:auth:user');//获取认证用户
```

参数与容器键同 `auth()`。

---

## token(prefix, headerName) — Token 认证

从请求头或 URL 查询参数 `?token=` 提取 token，调用验证器。

```php
container('#mw:auth:validators', [fn($token) => $user]);//设置验证器
middleware(token(), $handler);//使用中间件，从 Authorization 头提取
middleware(token('#mw:auth', 'X-Auth-Token'), $handler);//自定义请求头
```

参数：
- **`$prefix`**: `string` 默认 `'#mw:auth'` 容器键前缀
- **`$headerName`**: `string` 默认 `'Authorization'` 请求头名称（未取到时 fallback 查询参数 `token`）

容器配置：
- **`{prefix}:validators`**: `array` - 验证器数组，接收 `($token)` 返回用户信息
- **`{prefix}:user`**: `mixed` - 认证通过后写入用户信息

---

## jwt(prefix, algo) — JWT 认证

从 `Authorization: Bearer <token>` 提取 JWT，HMAC 验证签名后解码 payload。

```php
container('#mw:auth:secret', 'your-secret-key');//设置 HMAC 签名密钥
container('#mw:auth:validators', [fn($payload) => $user]);//设置验证器
middleware(jwt(), $handler);//使用中间件
container('#mw:auth:payload');//获取解码后的 JWT payload
container('#mw:auth:user');//获取认证用户
```

参数：
- **`$prefix`**: `string` 默认 `'#mw:auth'` 容器键前缀
- **`$algo`**: `string` 默认 `'HS256'` 签名算法，支持 `HS256`、`HS512`

容器配置：
- **`{prefix}:secret`**: `string` - HMAC 签名密钥
- **`{prefix}:validators`**: `array` - 验证器数组，接收 `($payload)` 返回用户信息
- **`{prefix}:user`**: `mixed` - 认证通过后写入用户信息
- **`{prefix}:payload`**: `array` - 自动写入解码后的 JWT payload

---

## apikey(prefix, headerName, queryName) — API Key 认证

从请求头或 URL 查询参数提取 API Key，调用验证器。

```php
container('#mw:auth:validators', [fn($apiKey) => $user]);//设置验证器
middleware(apikey(), $handler);//使用中间件
```

参数：
- **`$prefix`**: `string` 默认 `'#mw:auth'` 容器键前缀
- **`$headerName`**: `string` 默认 `'X-API-Key'` 请求头名称
- **`$queryName`**: `string` 默认 `'api_key'` URL 查询参数名

容器配置：
- **`{prefix}:validators`**: `array` - 验证器数组，接收 `($apiKey)` 返回用户信息
- **`{prefix}:user`**: `mixed` - 认证通过后写入用户信息

---

**通用中间件**

## cors(options) — CORS 跨域

为响应添加 CORS 头，`OPTIONS` 预检请求直接返回空响应。

```php
middleware(cors(), $handler);//基础使用
middleware(cors(['origin' => 'https://example.com']), $handler);//自定义配置
```

**$options**：
- **`origin`**: `string|array` 默认 `'*'` 允许的源，数组时随机选取
- **`methods`**: `string` 默认 `'GET,POST,PUT,DELETE,OPTIONS'` 允许的 HTTP 方法
- **`headers`**: `string` 默认 `'Content-Type,Authorization,X-CSRF-Token'` 允许的请求头
- **`credentials`**: `bool` 默认 `false` 是否允许发送凭证
- **`max-age`**: `int` 默认 `86400` 预检缓存时间（秒）

---

## csrf(verify) — CSRF 防护

- **`verify: false`**（默认）：生成 token 注入响应（数组加 `_token` 字段，对象加 `token` 属性）
- **`verify: true`**：校验请求中的 `_token` 或 `X-CSRF-Token` 头，不匹配返回 419

```php
middleware(csrf(), $handler);//生成 token
middleware(csrf(verify: true), $handler);//验证 token
```

容器配置：
- **`#mw:csrf:token`**: `string` - 存储/读取当前会话的 CSRF token

---

## error(debug) — 异常处理

捕获所有 `\Throwable` 异常。调试模式返回完整堆栈（含 `error`、`file`、`line`、`trace`、`type`），生产模式返回通用错误。

```php
middleware(error(debug: true), $handler);//开发环境，显示完整错误
middleware(error(), $handler);//生产环境，返回通用错误
```

参数：
- **`$debug`**: `bool` 默认 `false` 是否开启调试模式

---

## gzip(level) — Gzip 响应压缩

检查客户端 `Accept-Encoding: gzip`，压缩后内容比原内容小时启用压缩。自动跳过 `null`、`array`、`OPTIONS` 请求。

```php
middleware(gzip(), $handler);//默认压缩级别 6
middleware(gzip(9), $handler);//最高压缩级别
```

参数：
- **`$level`**: `int` 默认 `6` 压缩级别 1-9

---

## json(pretty) — JSON 格式化

将返回值转为 JSON 并设置 `Content-Type: application/json; charset=UTF-8`。

```php
middleware(json(), $handler);//默认输出
middleware(json(pretty: true), $handler);//格式化输出
```

参数：
- **`$pretty`**: `bool` 默认 `false` 是否格式化输出

---

## log(level) — 请求日志

记录请求方法、URI、状态码、耗时（ms）、内存（KB）。

```php
middleware(log(), $handler);//默认 info 级别
middleware(log('warning'), $handler);//自定义级别
```

参数：
- **`$level`**: `string` 默认 `'info'` 日志级别

容器配置：
- **`#out.response:code`**: `int` - 读取响应状态码（默认 200）

---

## rate(maxRequests, windowSeconds, key) — 接口限流

基于滑动窗口的 IP + 路由级别限流，默认使用 APCu 存储。

```php
middleware(rate(), $handler);//60次/分钟
middleware(rate(100, 60), $handler);//100次/分钟
middleware(rate(30, 60, 'api'), $handler);//自定义 key 前缀
```

参数：
- **`$maxRequests`**: `int` 默认 `60` 时间窗口内最大请求数
- **`$windowSeconds`**: `int` 默认 `60` 时间窗口大小（秒）
- **`$key`**: `string` 默认 `'rate'` 限流键名前缀

容器配置：
- **`#rate:storage`**: `callable` - 自定义存储，签名 `fn($key) => [...]`（读取）或 `fn($key, $value, $ttl) => 1`（写入）

```php
container('#rate:storage', fn($key) => [...]);//读取
container('#rate:storage', fn($key, $value, $ttl) => 1);//写入
```

---

## serve(root, cache) — 静态文件服务

根据 URI 在指定目录查找静态文件，自动设置 MIME 类型。目录自动追加 `index.html`。

```php
middleware(serve('/var/www/public'), $handler);//基础使用（无缓存头）
middleware(serve('/var/www/public', false), $handler);//强制不缓存
middleware(serve('/var/www/public', 31536000), $handler);//自定义 max-age
middleware(serve('/var/www/public', 'etag'), $handler);//ETag 条件缓存
middleware(serve('/var/www/public', 'modified'), $handler);//Last-Modified 条件缓存
middleware(serve('/var/www/public', ['control' => 'etag,modified', 'age' => 86400]), $handler);//组合策略
```

参数：
- **`$root`**: `string` 必填 静态文件根目录
- **`$cache`**: `null|false|int|string|array` 默认 `null` 缓存策略
  - `null`（默认）— 不输出 `Cache-Control`，浏览器自行决定
  - `false` — `Cache-Control: no-cache, no-store, must-revalidate`
  - `3600` / `86400` — `Cache-Control: public, max-age=N`
  - `'etag'` — ETag 条件缓存（`filemtime+filesize` 快速计算），命中返回 304
  - `'modified'` — Last-Modified 条件缓存，命中返回 304
  - `['control' => 'etag,modified', 'age' => 3600]` — 组合策略，`control` 逗号分隔，`age` 为 max-age 秒数

优先级：显式传参 > `container('#static:cache')` > 默认无缓存

内置 MIME 类型支持：`html`、`htm`、`txt`、`css`、`js`、`json`、`png`、`jpg`、`jpeg`、`gif`、`svg`、`ico`、`woff`、`woff2`、`ttf`、`zip`、`xml`

容器配置：
- **`#static:mimes`**: `array` - 扩展 MIME 类型
- **`#static:cache`**: `null|false|int|string|array` - 全局默认缓存策略，参数未传时回退到此配置

```php
container('#static:mimes', ['webp' => 'image/webp']);//扩展 MIME 类型
container('#static:cache', 86400);//全局默认缓存 1 天
```
