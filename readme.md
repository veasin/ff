# nx-tiny: A Minimal, Declarative Functional PHP Framework

---

## 中文版

### 描述
`nx-tiny` 是一个轻量级的、函数驱动的 PHP 框架，专为现代开发实践设计。它优先考虑**配置优于代码**，支持**尾调用优化**。

它避免了复杂的类层次结构和“魔法”行为，转而采用显式的、简单的全局函数。

### 核心哲学
*   **配置 > 代码**：所有操作都由中央配置容器驱动。
*   **函数式风格**：鼓励尾调用优化和可组合的函数链。
*   **声明式路由**：路由通过注解或脚本定义并自动生成。
*   **领域模型**：模型代表业务逻辑和关系，而不仅仅是数据库 ORM 实体。
*   **缓存即逻辑**：缓存集成在业务流程中，并内置回退机制。

### 安装

```bash
composer require veasin/nx-tiny
```

### 函数参考

#### container - 容器方法

支持双生命周期（持久/请求级）与延迟构建的配置容器，适用于 Swoole/FrankenPHP 等常驻内存场景。
在php-fpm或apache下，每次请求都是重置的，可以忽略^相关逻辑。

```php
// 获取所有配置
$all = container();

// 清空请求级配置
container(null);

// 清空持久级配置
container(null, true);
container(null, '^');

// 读取值（支持 . 分隔，先查请求级再查持久级）
$host = container('database.host');

// 仅读取持久级（^ 前缀）
$host = container('^database.host');

// 设置值（写入请求级）
container('database.host', 'localhost');
container('app.debug', true);

// 设置值（写入持久级，^ 前缀）
container('^database.host', 'localhost');

// 删除键（设置 null）
container('database.host', null);

// 批量读取（list 数组）
$values = container(['database.host', 'app.debug']);

// 批量设置（map 数组，写入请求级）
container([
    'database.host' => '127.0.0.1',
    'database.port' => 3306,
]);

// 批量持久设置
container(['k' => 'v'], true);
container(['k' => 'v'], '^');

// 数组 key 支持 ^ 修饰符单独控制
container(['^persist.k' => 'v', 'request.k' => 'v']);

// 闭包工厂：存入闭包，用 * 后缀执行
container('version', fn() => file_get_contents('version.txt'));
$closure = container('version');  // 返回闭包本身
$result  = container('version*'); // 执行闭包返回结果

// 非闭包值忽略 *
container('plain', 'string');
container('plain*');  // 返回 'string'
```

#### env - 环境变量读取

支持系统环境变量、`$_ENV`、`.env` 文件三种来源。

```php
// 读取环境变量
$debug = env('APP_DEBUG');   // 自动类型转换: 'true' → true, 'false' → false, 'null' → null
$host  = env('DB_HOST');     // 不存在返回 null
$name  = env('APP_NAME');    // 字符串原样返回

// .env 文件路径可通过容器配置
container('#env', '/path/to/.env');
env('APP_KEY');

// 默认自动查找：从 src/ 向上搜索 .env（最多 3 层）
```

> .env 配置：`container('#env', '/path/to/.env')`

#### args - 命令行参数解析

```php
// 字符串输入
$args = args('-v --file=test.php input.txt');
// 结果: ['v' => true, 'file' => 'test.php', 'input.txt']

// 数组输入
$args = args(['-abc', '--verbose', '--name=John', 'data.txt']);
// 结果: ['a' => true, 'b' => true, 'c' => true, 'verbose' => true, 'name' => 'John', 'data.txt']

// 带引号的值
$args = args('--message="Hello World" --path=\'/usr/local\'');
// 结果: ['message' => 'Hello World', 'path' => '/usr/local']
```

#### method - HTTP方法获取/检查

```php
// 获取当前请求方法
$method = method();  // 返回: 'get', 'post', 'cli' 等

// 检查是否匹配指定方法
if (method('POST')) {
    // 处理 POST 请求
}

// 预置请求方法（通过容器）
container('#method', 'put');
```

> 缓存键：`#method`

#### from - 从指定来源获取原始值，支持来源：query|cookie|file|params|header|input|body

```php
// 从 Body 获取原始值
$id = from('id', 'body');

// 从 Query 获取原始值
$name = from('name', 'query');

// 从 Header 获取原始值
$token = from('authorization', 'header');

// 直接使用数组作为来源
$data = from('id', ['id' => 123, 'name' => 'test']);

// 获取整个来源
$body = from(null, 'body');

// 批量获取
$result = from(['id', 'name'], 'query');  // ['id' => null, 'name' => 'test']

// 预置输入数据（通过容器缓存）
container('#in.input', ['method' => 'get', 'uri' => '/test', 'params' => []]);
container('#in.params', ['id' => 123]);  // 预置路由参数
container('#in.body', ['name' => 'test']);  // 预置请求体
container('#in.headers', ['Authorization' => 'Bearer xxx']);  // 预置请求头

// 扩展 content-type 解析器
container('#in.content', [
    'application/xml' => fn($raw) => simplexml_load_string($raw),
    'default' => fn($raw) => ['raw' => $raw],
]);
```

> 缓存键：`#in.input`、`#in.params`、`#in.body`、`#in.headers`、`#in.raw`、`#in.content`

#### filter - 数据验证与转换

```php
// 类型转换
filter('123', 'int');        // 返回 123 (int)
filter('true', 'bool');      // 返回 true
filter('{"a":1}', 'json');   // 返回 ['a' => 1]

// 验证规则
filter('hello@example.com', 'email');  // 返回邮箱字符串
filter('150', 'int', '>100', '<200');  // 返回 150
filter('on', 'bool');                  // 返回 true

// 逗号分隔的组合规则
filter('150', 'int,>100,<200');  // 返回 150

// 自定义验证
filter('abc', fn($v) => strlen($v) > 2);  // 返回 'abc'
filter(10, 'int', '>5');                  // 返回 10
filter(3, 'int', '>5');                   // 返回 null (验证失败)

// 扩展规则（通过容器配置 #filter）
container('#filter', [
    'phone' => [null, null, [fn($v) => preg_match('/^1\d{10}$/', $v)]],
]);
filter('13800138000', 'phone');  // 返回 '13800138000'
```

> 扩展方式：`container('#filter', [...])`

#### input - 输入数据获取（获取from+验证filter）

```php
// 获取并验证（多个规则）
$age = input('age', 'query', 'int', '>=18', '<=100');

// 组合规则
$age = input('age', 'body,int,>=18,<=100');

// 批量获取
$data = input(['id' => 'int,>0', 'name' => 'str']);
```

#### output - 输出数据

```php
// JSON 输出
output(['status' => 'ok', 'data' => [1, 2, 3]]);

// 设置状态码
output(['error' => 'not found'], 404);

// 指定格式输出
output($data, 'json');

// 输出视图
output($viewData, 'view', 'template.php');
output($viewData, 'view', ['file' => 'template.php']);

// 文件输出（展示文件）
output(null, 'file', '/path/to/file.pdf');

// 文件下载
output(true, 'file', '/path/to/file.pdf');

// 带响应头
output(['token' => $token], 200, ['Authorization' => 'Bearer xxx']);

// 无参调用触发输出（用于 worker 模式显式发送）
output();

// 扩展输出格式（通过容器配置 #out.formats）
container('#out.formats', [
    'xml' => function($response, $formats) {
        $response['headers']['Content-Type'] = 'application/xml';
        $response['body'] = xml_encode($response['body']);
        $formats['http']($response, $formats);
    },
]);
output($data, 'xml');

// 自定义渲染回调
container('#out.callback', function($response) {
    echo json_encode($response['body']);
});
```

> 扩展方式：`container('#out.formats', [...])`  
> 回调方式：`container('#out.callback', fn($response) => ...)`

#### route - 路由匹配

```php
// 基础路由
route('GET:/users', function($next) {
    output(['users' => []]);
});

// 带参数 (:param 或 {param})
route('GET:/user/:id', function() {
    $id = input('id', 'params');
    output(['id' => $id]);
});

// POST 路由
route('POST:/api/user', function() {
    $name = input('name', 'body');
    output(['created' => $name]);
});

// 路由映射数组
route([
    'get:/api/list' => function() { return 'list'; },
    'post:/api/create' => function() { return 'create'; },
]);

// 通配符路由
route('GET:/api/*', function() {
    // 匹配 /api 下的所有路径
});

// CLI 路由
route('cli:verbose', function() { /* ... */ });
route('cli:file=*', function() { /* ... */ });
```

#### cache - 多级缓存

```php
// APCu 缓存
$result = cache('APCu', function() {
    return db('SELECT * FROM users');
});

// Redis 缓存
$result = cache('Redis', function() {
    return expensiveOperation();
});

// 带 TTL
$result = cache(['fn' => 'Redis', 'ttl' => 3600], function() {
    return $data;
});

// 组合缓存（按顺序尝试）
$result = cache('APCu', 'Redis', function() {
    return $data;
});

// 配置方式（通过容器 cache）
container('cache', [
    'user_list' => ['APCu', 1800],  // APCu, TTL 30分钟
    'api_data' => ['Redis', 3600, 'prefix_'],  // Redis, TTL 1小时, 自定义前缀
]);
$result = cache('user_list', function() {
    return db('SELECT * FROM users', [], 'list');
});
```

> 配置方式：`container('cache', [...])`  
> Redis 配置：`container('config.redis', ['host' => '127.0.0.1', 'port' => 6379, 'password' => '', 'database' => 0])`

#### db - 数据库操作

```php
// 查询单行
$user = db('SELECT * FROM users WHERE id = ?', [1], 'row');

// 查询列表
$users = db('SELECT * FROM users', [], 'list');

// 查询单个值
$count = db('SELECT COUNT(*) FROM users', [], 'value');

// 查询单列（返回数组）
$names = db('SELECT name FROM users', [], 'column');

// 查询键值对
$pairs = db('SELECT id, name FROM users', [], 'pairs');

// 查询分组结果
$grouped = db('SELECT status, COUNT(*) FROM users GROUP BY status', [], 'group');

// 插入并获取ID
$id = db('INSERT INTO users (name) VALUES (?)', ['John'], 'id');

// 更新并获取影响行数
$count = db('UPDATE users SET name = ? WHERE id = ?', ['Jane', 1], 'count');

// 批量插入
db('INSERT INTO users (name) VALUES (?), (?)', [['John'], ['Jane']], 'ok');

// 执行模式（返回 PDOStatement）
$stmt = db('SELECT * FROM users', [], true);

// 自定义处理
$result = db('SELECT * FROM users', [], fn($stmt, $pdo) => $stmt->fetchAll());
```

**事务支持**：

```php
// 开启事务
db('BEGIN');

// 提交事务
db('COMMIT');

// 回滚事务
db('ROLLBACK');

// 保存点
db('SAVEPOINT sp1');

// 回滚到保存点
db('ROLLBACK TO SAVEPOINT sp1');
```

**配置数据库连接**：

```php
container('db.default', [
    'dsn' => 'mysql:host=localhost;dbname=test',
    'username' => 'root',
    'password' => '',
    'options' => [],
]);

// 使用命名配置
$user = db('SELECT * FROM users WHERE id = ?', [1], 'row', 'default');
```

> 配置方式：`container('db.{name}', [...])`

配合 [nx-sql](https://github.com/veasin/nx-sql) 使用：

```php
use nx\helpers\sql;

// 插入数据并获取ID
$id = db(sql::table('users')->insert(['name' => 'John', 'email' => 'john@test.com']), 'id');

// 查询单行
$user = db(sql::table('users')->where(['id' => 1])->select(), 'row');

// 条件查询
$activeUsers = db(sql::table('users')->where(['status' => 1])->select(), 'list');

// 更新数据
$affected = db(sql::table('users')->where(['id' => 1])->update(['name' => 'Jane']), 'count');

// 删除数据
$affected = db(sql::table('users')->where(['id' => 1])->delete(), 'count');
```

#### test - 轻量级测试

```php
// 直接比较
test('数字比较', 5, 5);

// 函数返回值
test('函数返回值', fn() => 2+2, 4);

// 断言函数
test('范围判断', 10, fn($v) => $v > 5);

// 数组验证
test('数组验证', ['a' => 1], function($value) {
    return isset($value['a']) && $value['a'] === 1;
});

// 函数作为待测值
test('函数返回值测试', fn() => 2+2, 4);
```

#### name - 命名配置管理

```php
// 基础用法
$key = name('user.id');  // 返回 'user.id'

// 命名空间
container('name', ['cache' => ['user' => 'cache:user:{uid}']]);
$key = name('user', ['uid' => 123], 'cache');  // 返回 'cache:user:123'
```

> 配置方式：`container('name', [...])`

#### log - 日志函数

```php
// 基础用法（默认 level 为 info）
log('用户登录');

// 指定 level
log('发生错误', 'error');

// context 为字符串时作为 level
log('警告信息', 'warning');

// 使用 context 替换占位符 {key}
log('用户 {name} 登录', ['name' => 'admin']);

// 同时使用 context 和 level
log('错误: {msg}', ['msg' => '连接失败'], 'error');

// 非 string 消息自动 json
log(['a' => 1, 'b' => 2]);

// 支持 Stringable 对象
log(new StringableClass());

// 注入 PSR Logger
container('#log', $psrLogger);

// 注入闭包（fn 不会被容器自动执行）
container('#log.fn', fn($level, $message, $context) => ...);
```

> PSR Logger 方式：`container('#log', $logger)`  
> 闭包方式：`container('#log.fn', fn(...) => ...)`

---

### 预制中间件

所有预制中间件返回 `callable`，直接传入 `middleware()` 或 `run()` 使用：

```php
middleware(cors(), auth(), log(), $handler);
```

#### auth(prefix, realm) — HTTP Basic 认证

旧版 Basic 认证。从 `Authorization: Basic` 头提取用户名密码，调用验证器。

```php
container('#mw:auth:validators', [fn($user, $pass) => true]);
middleware(auth(), $handler);
// 获取用户: container('#mw:auth:user')
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `$prefix` | `#mw:auth` | 容器键前缀 |
| `$realm` | `Protected` | WWW-Authenticate realm |

| 容器键 | 说明 |
|---|---|
| `{prefix}:validators` | 验证器数组，接收 `($user, $pass)` 返回 bool |
| `{prefix}:user` | 认证通过后写入用户信息 |

#### basic(prefix, realm) — HTTP Basic 认证（推荐）

新版 Basic 认证。验证器返回值直接存入 user（可返回用户对象而非 `true`）。

```php
container('#mw:auth:validators', [fn($user, $pass) => $user]);
middleware(basic(), $handler);
```

参数与容器键同 `auth()`。

#### token(prefix, headerName) — Token 认证

从 `Authorization` 头或 URL 查询参数 `?token=` 提取 token。

```php
container('#mw:auth:validators', [fn($token) => $user]);
middleware(token(), $handler);
middleware(token('#mw:auth', 'X-Auth-Token'), $handler);  // 自定义请求头
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `$prefix` | `#mw:auth` | 容器键前缀 |
| `$headerName` | `Authorization` | 请求头名称（未取到时 fallback 查询参数 `token`） |

| 容器键 | 说明 |
|---|---|
| `{prefix}:validators` | 验证器数组，接收 `($token)` |
| `{prefix}:user` | 认证通过后写入用户信息 |

#### jwt(prefix, algo) — JWT 认证

从 `Authorization: Bearer <token>` 提取 JWT，HMAC 验证签名后解码 payload。

```php
container('#mw:auth:secret', 'your-secret-key');
container('#mw:auth:validators', [fn($payload) => $user]);
middleware(jwt(), $handler);
// 获取 payload: container('#mw:auth:payload')
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `$prefix` | `#mw:auth` | 容器键前缀 |
| `$algo` | `HS256` | 签名算法，支持 `HS256`、`HS512` |

| 容器键 | 说明 |
|---|---|
| `{prefix}:secret` | HMAC 签名密钥 |
| `{prefix}:validators` | 验证器数组，接收 `($payload)` |
| `{prefix}:user` | 认证通过后写入用户信息 |
| `{prefix}:payload` | 自动写入解码后的 JWT payload |

#### apikey(prefix, headerName, queryName) — API Key 认证

从请求头或 URL 查询参数提取 API Key。

```php
container('#mw:auth:validators', [fn($apiKey) => $user]);
middleware(apikey(), $handler);
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `$prefix` | `#mw:auth` | 容器键前缀 |
| `$headerName` | `X-API-Key` | 请求头名称 |
| `$queryName` | `api_key` | URL 查询参数名 |

| 容器键 | 说明 |
|---|---|
| `{prefix}:validators` | 验证器数组，接收 `($apiKey)` |
| `{prefix}:user` | 认证通过后写入用户信息 |

#### cors(options) — CORS 跨域

为响应添加 CORS 头，`OPTIONS` 预检请求直接返回空响应。

```php
middleware(cors(), $handler);
middleware(cors(['origin' => 'https://example.com']), $handler);
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `origin` | `'*'` | 允许的源，数组时随机选取 |
| `methods` | `'GET,POST,PUT,DELETE,OPTIONS'` | 允许的 HTTP 方法 |
| `headers` | `'Content-Type,Authorization,X-CSRF-Token'` | 允许的请求头 |
| `credentials` | `false` | 是否允许发送凭证 |
| `max-age` | `86400` | 预检缓存时间（秒） |

#### csrf(verify) — CSRF 防护

- `verify: false`（默认）：生成 token 注入响应（数组加 `_token` 字段，对象加 `token` 属性）
- `verify: true`：校验请求中的 `_token` 或 `X-CSRF-Token` 头，不匹配返回 419

```php
middleware(csrf(), $handler);          // 生成 token
middleware(csrf(verify: true), $handler); // 验证 token
```

| 容器键 | 说明 |
|---|---|
| `#mw:csrf:token` | 存储/读取当前会话的 CSRF token |

#### error(debug) — 异常处理

捕获所有 `\Throwable` 异常。调试模式返回完整堆栈，生产模式返回通用错误。

```php
middleware(error(debug: true), $handler);  // 开发环境
middleware(error(), $handler);             // 生产环境
```

#### gzip(level) — Gzip 响应压缩

检查客户端 `Accept-Encoding: gzip`，压缩后内容比原内容小时启用压缩。

```php
middleware(gzip(), $handler);
middleware(gzip(9), $handler);   // 最高压缩级别
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `$level` | `6` | 压缩级别 1-9 |

#### json(pretty) — JSON 格式化

将返回值转为 JSON 并设置 `Content-Type: application/json; charset=UTF-8`。

```php
middleware(json(), $handler);
middleware(json(pretty: true), $handler);  // 格式化输出
```

#### log(level) — 请求日志

记录请求方法、URI、状态码、耗时（ms）、内存（KB）。

```php
middleware(log(), $handler);
middleware(log('warning'), $handler);
```

| 容器键 | 说明 |
|---|---|
| `#out.response:code` | 读取响应状态码（默认 200） |

#### rate(maxRequests, windowSeconds, key) — 接口限流

基于滑动窗口的 IP + 路由级别限流，默认使用 APCu 存储。

```php
middleware(rate(), $handler);              // 60次/分钟
middleware(rate(100, 60), $handler);       // 100次/分钟
middleware(rate(30, 60, 'api'), $handler); // 自定义 key 前缀
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `$maxRequests` | `60` | 时间窗口内最大请求数 |
| `$windowSeconds` | `60` | 时间窗口大小（秒） |
| `$key` | `'rate'` | 限流键名前缀 |

自定义存储（通过容器）：
```php
container('#rate:storage', fn($key) => [...]);           // 读取
container('#rate:storage', fn($key, $value, $ttl) => 1); // 写入
```

#### serve(root, map) — 静态文件服务

根据 URI 在指定目录查找静态文件，自动设置 MIME 类型和缓存头。

```php
middleware(serve('/var/www/public'), $handler);
// 扩展名映射（如将 .html 指向 index.php）
middleware(serve('/var/www/public', ['html' => 'index.php']), $handler);
```

| 参数 | 默认值 | 说明 |
|---|---|---|
| `$root` | 必填 | 静态文件根目录 |
| `$map` | `[]` | 扩展名到目标文件的映射 |

扩展 MIME 类型：
```php
container('#static:mimes', ['webp' => 'image/webp']);
```
```
