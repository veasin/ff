# nx-tiny: A Minimal, Declarative Functional PHP Framework

---

## 中文版

### 描述
`nx-tiny` 是一个轻量级的、纯函数驱动的 PHP 微框架，专为 PHP 8.4+ 设计。核心设计理念：**一函数多用、函数即模块、容器即状态、组合即流程**。

无类、无依赖注入、无服务提供者、无注解路由——全部由命名空间函数组成，极致精简。

### 核心哲学
*   **零类架构**：全部使用命名空间函数，无 class、无 DI 容器、无服务提供者
*   **一函数多用**：同一函数通过参数个数、类型、值实现不同语义
*   **容器即状态**：`container()` 是唯一全局状态管理器，支持请求级/持久级双生命周期
*   **组合即流程**：`middleware()` 洋葱模型、`hump()` 链式调用、`cache()` 多级回退，函数组合编排一切
*   **失败返回 null**：统一错误语义，管道中自然短路穿透
*   **扩展走容器**：所有可扩展能力通过 `container()` 注入，不增加函数参数签名

### 安装

```bash
composer require veasin/nx-tiny
```

### 函数参考

---

**基础设施**

#### container - 容器方法

支持双生命周期（持久/请求级）与延迟构建的配置容器，适用于 Swoole/FrankenPHP 等常驻内存场景。在 php-fpm 或 apache 下，每次请求都是重置的，可以忽略相关逻辑。

```php
container(null);//清空请求级配置
container(null, true);//清空持久级配置
container('^database.host');//仅读取持久级（^ 前缀）
container('database.host', 'localhost');//写入请求级
container('^database.host', 'localhost');//写入持久级
container('database.host', null);//删除键
container(['database.host', 'app.debug']);//批量读取
container(['k' => 'v']);//批量设置（请求级）
container(['k' => 'v'], '^');//批量持久设置
container(['^persist.k' => 'v', 'request.k' => 'v']);//混合级别批量设置
container('version', fn() => file_get_contents('version.txt'));//闭包工厂
container('version*');//执行闭包返回结果
container('plain*');//非闭包值忽略 *，返回 'string'
```

---

#### env - 环境变量读取

支持系统环境变量、`$_ENV`、`.env` 文件三种来源，自动类型转换（`'true'` → `true`、`'false'` → `false`、`'null'` → `null`、`'empty'` → `''`）。

```php
$host = env('DB_HOST');//不存在返回 null
env('APP_KEY');//读取 .env 文件
```

容器配置：
- **`#env`**: `string` - `.env` 文件路径，默认从 `src/` 向上搜索（最多 3 层）

---

#### name - 命名配置管理

```php
$key = name('user.id');//返回 'user.id'
$key = name('user', ['uid' => 123], 'cache');//命名空间替换，返回 'cache:user:123'
```

容器配置：
- **`#name`**: `array` - 命名空间模板配置

```php
container('#name', ['cache' => ['user' => 'cache:user:{uid}']]);
```

---

#### args - 命令行参数解析

解析命令行参数，支持短选项、长选项和引号值。

```php
$args = args('-v --file=test.php input.txt');
//['v' => true, 'file' => 'test.php', 'input.txt']
$args = args(['-abc', '--name=John', 'data.txt']);
//['a' => true, 'b' => true, 'c' => true, 'name' => 'John', 'data.txt']
$args = args('--message="Hello World"');
//['message' => 'Hello World']
```

---

#### safe - 安全调用

封装 try/catch 模式，失败返回 `null`，省去重复的异常处理模板代码。

```php
$users = safe(fn() => db('SELECT * FROM users'));//无参调用
$user = safe(fn($id) => db('SELECT * FROM users WHERE id=?', [$id]), 1);//带参数调用
$result = safe(fn($a, $b) => $a / $b, 10, 0);//多参数，返回 null
$data = safe(fn() => json_decode($raw, true, 512, JSON_THROW_ON_ERROR));//异常时静默降级
```

---

**输入层**

#### input - 输入数据获取

获取输入并验证，组合 `from()` + `filter()`。未指定来源时默认 `body`。

```php
$age = input('age', 'query', 'int', '>=18', '<=100');//单值：来源+规则
$age = input('age', 'body,int,>=18,<=100');//单值：组合规则字符串
$data = input(['id' => 'int,>0', 'name' => 'str']);//批量：map 数组+规则
$list = input(['id', 'name'], 'body');//批量：list 数组+来源
```

---

#### from - 从指定来源获取原始值

支持来源：`query` | `cookie` | `file` | `params` | `header` | `input` | `body`。

```php
$id = from('id', 'body');//从 Body 获取
$name = from('name', 'query');//从 Query 获取
$token = from('authorization', 'header');//从 Header 获取
$data = from('id', ['id' => 123, 'name' => 'test']);//直接使用数组作为来源
$body = from(null, 'body');//获取整个来源
$result = from(['id', 'name'], 'query');//批量获取
```

容器配置：
- **`#in.input`**: `array` - 预置输入数据（`method`、`uri`、`params`）
- **`#in.params`**: `array` - 预置路由参数
- **`#in.body`**: `array` - 预置请求体
- **`#in.headers`**: `array` - 预置请求头
- **`#in.raw`**: `string` - 预置原始输入
- **`#in.content`**: `array` - 扩展 content-type 解析器

---

#### filter - 数据验证与转换

类型转换与验证规则链，规则可逗号分隔组合。

```php
filter('123', 'int');//返回 123 (int)
filter('true', 'bool');//返回 true
filter('{"a":1}', 'json');//返回 ['a' => 1]
filter('hello@example.com', 'email');//邮箱验证
filter('150', 'int,>100,<200');//逗号分隔组合规则
filter(10, 'int', '>5');//返回 10
filter(3, 'int', '>5');//返回 null（验证失败）
filter('abc', fn($v) => strlen($v) > 2);//自定义验证，返回 'abc'
```

容器配置：
- **`#filter`**: `array` - 扩展规则，格式 `[name => [type, default, [callable]]]`

```php
container('#filter', ['phone' => [null, null, [fn($v) => preg_match('/^1\d{10}$/', $v)]]]);
filter('13800138000', 'phone');//返回 '13800138000'
```

---

**输出层**

#### output - 输出数据

支持多种格式和模板，核心签名 `output($data, $set)`。

```php
output(['status' => 'ok']);//默认 JSON 输出
output(null, 201);//只设置状态码（int 简写）
output(Status::NotFound);//使用 Status enum
output($data, ['type' => 'json']);//指定格式
output($data, ['type' => 'view', 'file' => 'template.php']);//视图模板
output($data, ['type' => 'file', 'file' => '/path/to/photo.jpg']);//文件输出
output($data, 'template.php');//视图模板 string 简写
output(true, ['type' => 'file', 'file' => '/path/to/file.pdf']);//文件下载（Content-Disposition）
output();//无参触发发送
```

**$set**：
- **`code`**: `int` 默认 `200` HTTP 状态码
- **`type`**: `string` 默认 `'json'` 输出格式，内置 `json`/`view`/`file`
- **`file`**: `string` - 文件路径，`type=view` 时作模板，`type=file` 时作下载文件
- **`headers`**: `array` 默认 `[]` HTTP 响应头键值对
- **`message`**: `string` 默认 `''` HTTP 状态消息
- **`pretty`**: `bool` 默认 `false` JSON 是否美化输出

容器配置：
- **`#out.type.{name}`**: `callable` - 扩展输出格式，签名 `fn(array $response): array`
- **`#out.callback`**: `callable` - 自定义渲染回调，签名 `fn($response) => ...`

```php
//扩展输出格式
container('#out.type.xml', function(array $response): array {
    $response['headers']['Content-Type'] = 'application/xml';
    $response['body'] = xml_encode($response['body']);
    return $response;
});
output($data, ['type' => 'xml']);

//自定义渲染回调
container('#out.callback', function($response) {
    echo json_encode($response['body']);
});
```

---

**流程控制**

#### route - 路由匹配

支持 RESTful 路由、参数占位符、通配符和 CLI 路由。

```php
route('GET:/users', function($next) { output(['users' => []]); });//基础路由
route('GET:/user/:id', function() { ... });//带参数（:param 或 {param}）
route('POST:/api/user', function() { ... });//POST 路由
route(['get:/api/list' => fn() => 'list', 'post:/api/create' => fn() => 'create']);//路由映射数组
route('GET:/api/*', function() { ... });//通配符路由
route('cli:verbose', function() { ... });//CLI 路由
route(true);//开启延时模式
route();//触发执行收集的路由
route(null);//清空已收集的路由
```

延时执行模式：
```php
route(true);//开启
route('GET:/api/items', fn($next) => output(loadItems()));
route('POST:/api/items', fn($next) => { /* ... */ });
route();//统一触发
```

---

#### middleware - 中间件执行引擎

洋葱模型中间件执行器，支持阻断（不调 `$next` 则终止）。参数可以是闭包、文件路径或其他值。

```php
middleware(cors(), auth(), $handler);//执行中间件列表
middleware(fn($next) => $next('value'));//闭包需接收 $next 并调用
middleware('/path/to/file.php', $handler);//文件路径，通过 $next 变量调用下一层
middleware('fallback');//非可调用值作为初始值透传
```

**$next 规则：**
- 中间件按顺序执行，调 `$next(...)` 才继续下一层
- 不调 `$next` 则终止，返回当前中间件的返回值
- 多次调 `$next` 只有第一次生效，后续直接返回第一次的结果

---

#### hump - 链式中间件执行器

洋葱模型变体，支持链式调用但不支持阻断。不调 `$next` 也会自动继续执行，并将当前返回值传给下一个。

```php
hump(cors(), auth(), $handler);//执行中间件列表
hump(fn($next) => $next('value'));//不调 $next 自动继续，返回值传给下一层
hump('/path/to/file.php', $handler);//文件路径
```

---

#### hook - 钩子系统

注册/触发分离的钩子系统，与容器集成。生命周期模式下 `output()` 等函数自动挂载。

```php
hook(true);//开启钩子模式（持久级，默认序列 ['after', 'end']）
hook(true, ['after', 'end']);//自定义默认序列
hook('after', fn() => output(['status' => 'ok']));//注册回调（请求级）
hook();//触发默认序列（after → end）
hook('after');//触发单个钩子
hook(['after', 'end']);//自定义触发顺序
hook('custom', function() { echo 'hello'; });//独立注册
hook('custom');//独立触发
hook('after', null);//清空指定钩子
hook(null);//清空所有钩子
```

容器配置：
- **`#hook.{name}`**: `array` - 钩子回调列表，请求结束时 `container(null)` 自动清空
- **`^#hook`**: `array` - 默认序列，持久级存储，跨请求保持

---

**缓存**

#### cache - 多级缓存链

接收中间件工厂或值，按顺序链式执行。返回 `null` 的中间件自动穿透到下一层。

```php
cache(apcu('key', middleware: true), 'fallback value');//简单兜底
cache(apcu('key', middleware: ['ttl' => 3600]), fn($next) => render());//工厂闭包
cache(apcu('key', middleware: 60), redis('key', middleware: 60), fn($next) => db('SELECT ...'));//多级链
```

---

#### apcu - APCu 缓存驱动

支持 CRUD 和中间件工厂两种模式，统一签名 `apcu($key, $value, $set, $middleware)`。默认 TTL 为 0（永不过期）。

```php
apcu('key');//单键读取
apcu('key', 'value');//单键写入
apcu('key', null);//单键删除
apcu(null);//清空全部
apcu('key', 'value', 60);//写入 + TTL（int 简写）
apcu('key', 'value', ['ttl' => 60, 'config' => 'cfg']);//写入 + TTL + 配置名
apcu('key', 'value', 'cfg');//写入 + 配置名（string 简写）
apcu(['k1', 'k2']);//批量读取
apcu(['k1' => 'v1']);//批量写入
```

中间件工厂模式：
```php
cache(apcu('key', middleware: true), fn($next) => render());
cache(apcu('key', middleware: 60), fn($next) => render());
cache(apcu('key', 'fallback', middleware: true), fn($next) => null);
```

容器配置：
- **`#cache.apcu.{name}`**: `array` - 命名配置，支持 `ttl`、`prefix`、`config`

---

#### redis - Redis 缓存驱动

签名与 `apcu()` 完全一致，支持 CRUD 和中间件工厂两种模式。

```php
redis('key');//单键读取
redis('key', 'value');//单键写入
redis('key', null);//单键删除
redis('key', 'value', 60);//写入 + TTL
redis('key', 'value', ['ttl' => 60, 'config' => 'cfg']);//写入 + TTL + 配置名
redis('key', 'value', 'cfg');//写入 + 配置名（string 简写）
redis(['k1', 'k2']);//批量读取
redis(['k1' => 'v1']);//批量写入
```

中间件工厂模式：
```php
cache(redis('key', middleware: true), fn($next) => expensiveOp());
cache(redis('key', 'fallback', middleware: 60), fn($next) => null);
```

容器配置：
- **`config.redis`**: `array` - Redis 连接配置，支持 `host`、`port`、`password`、`database`
- **`#cache.redis.{name}`**: `array` - 命名配置，支持 `ttl`、`prefix`

---

**数据库**

#### db - 数据库操作

基于 PDO 的数据库操作，支持多种返回模式、事务和命名配置。

```php
$user = db('SELECT * FROM users WHERE id = ?', [1], 'row');//查询单行
$users = db('SELECT * FROM users', [], 'list');//查询列表
$count = db('SELECT COUNT(*) FROM users', [], 'value');//查询单个值
$names = db('SELECT name FROM users', [], 'column');//查询单列
$pairs = db('SELECT id, name FROM users', [], 'pairs');//查询键值对
$grouped = db('SELECT status, COUNT(*) FROM users GROUP BY status', [], 'group');//分组结果
$id = db('INSERT INTO users (name) VALUES (?)', ['John'], 'id');//插入获取 ID
$count = db('UPDATE users SET name = ? WHERE id = ?', ['Jane', 1], 'count');//更新影响行数
$stmt = db('SELECT * FROM users', [], true);//返回 PDOStatement
$ok = db('INSERT INTO users (name) VALUES (?), (?)', [['John'], ['Jane']], 'ok');//批量插入
$result = db('SELECT * FROM users', [], fn($stmt, $pdo) => $stmt->fetchAll());//自定义处理
```

事务支持：
```php
db('BEGIN');//开启事务
db('COMMIT');//提交事务
db('ROLLBACK');//回滚事务
db('SAVEPOINT sp1');//保存点
db('ROLLBACK TO SAVEPOINT sp1');//回滚到保存点
```

容器配置：
- **`db.{name}`**: `array` - 数据库连接配置，支持 `dsn`、`username`、`password`、`options`

```php
container('db.default', ['dsn' => 'mysql:host=localhost;dbname=test', 'username' => 'root', 'password' => '']);
$user = db('SELECT * FROM users WHERE id = ?', [1], 'row', 'default');//使用命名配置
```

配合 [nx-sql](https://github.com/veasin/nx-sql) 使用：
```php
$id = db(sql::table('users')->insert(['name' => 'John']), 'id');//插入
$user = db(sql::table('users')->where(['id' => 1])->select(), 'row');//查询
$affected = db(sql::table('users')->where(['id' => 1])->update(['name' => 'Jane']), 'count');//更新
$affected = db(sql::table('users')->where(['id' => 1])->delete(), 'count');//删除
```

---

**国际化**

#### i18n - 多语言翻译

支持占位符替换和强制语言，框架翻译键格式 `#模块:key`。

```php
i18n(lang: 'en_US');//设置当前语言
$msg = i18n('#error:internal');//框架翻译
$msg = i18n('#error:internal', 'en_US');//强制语言
$msg = i18n('welcome', ['name' => '张三']);//{name} 占位符替换
$msg = i18n('dot.key');//. 自动转 _
```

容器配置：
- **`i18n.{lang}.{key}`**: `string` - 用户翻译，可增量覆盖框架默认
- **`^i18n.lang`**: `string` - 持久化当前语言

```php
container('i18n.zh_CN.#error:internal', '自定义');
container('^i18n.lang', 'en_US');
```

---

**开发调试**

#### log - 日志函数

```php
log('用户登录');//基础用法，默认 level 为 info
log('发生错误', 'error');//指定 level
log('用户 {name} 登录', ['name' => 'admin']);//{key} 占位符替换
log('错误: {msg}', ['msg' => '连接失败'], 'error');//同时使用 context 和 level
log(['a' => 1, 'b' => 2]);//非 string 消息自动 json
log(new StringableClass());//支持 Stringable 对象
```

容器配置：
- **`#log`**: `object|callable` - PSR Logger 对象或闭包，签名 `fn($level, $message, $context)`

```php
container('#log', $psrLogger);//注入 PSR Logger
container('#log', fn($level, $message, $context) => ...);//注入闭包
```

---

#### test - 轻量级测试

支持直接比较、闭包断言和异常类型断言。value/assign 执行中抛异常被捕获，异常对象参与比较（异常 vs 具体值必然失败），输出时显示异常 message。

```php
test('数字比较', 5, 5);//直接比较
test('加法', fn() => 2+2, 4);//value 是闭包
test('范围判断', 10, fn($v) => $v > 5);//assign 是断言函数
test('除零类型', fn() => 1/0, DivisionByZeroError::class);//异常类型断言
test('除零', fn() => 1/0, fn($v) => $v instanceof DivisionByZeroError);//闭包处理异常
test();//执行所有测试并输出
test(null);//清空所有测试用例
```

**ANSI 颜色标记：**
- 小写=标准色：`k`黑 `r`红 `g`绿 `y`黄 `b`蓝 `m`品 `c`青 `w`白 `n`灰
- 大写=亮色：`K`亮黑 `R`亮红 `G`亮绿 `Y`亮黄 `B`亮蓝 `M`亮品 `C`亮青 `W`亮白
- `[r:w]` 前景红底白，`[ :]` 重置前景，`[: ]` 重置背景，`[ : ]` 重置全部，`[:]` 重置全部简写

---

### 预制中间件

所有预制中间件返回 `callable`，直接传入 `middleware()` 或 `hump()` 使用：

```php
middleware(cors(), auth(), log(), $handler);
```

**认证相关**

#### auth(prefix, realm) — HTTP Basic 认证

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

#### basic(prefix, realm) — HTTP Basic 认证（推荐）

新版 Basic 认证。验证器返回值直接存入 user（可返回用户对象而非 `true`）。

```php
container('#mw:auth:validators', [fn($user, $pass) => $user]);//设置验证器
middleware(basic(), $handler);//使用中间件
container('#mw:auth:user');//获取认证用户
```

参数与容器键同 `auth()`。

---

#### token(prefix, headerName) — Token 认证

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

#### jwt(prefix, algo) — JWT 认证

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

#### apikey(prefix, headerName, queryName) — API Key 认证

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

#### cors(options) — CORS 跨域

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

#### csrf(verify) — CSRF 防护

- **`verify: false`**（默认）：生成 token 注入响应（数组加 `_token` 字段，对象加 `token` 属性）
- **`verify: true`**：校验请求中的 `_token` 或 `X-CSRF-Token` 头，不匹配返回 419

```php
middleware(csrf(), $handler);//生成 token
middleware(csrf(verify: true), $handler);//验证 token
```

容器配置：
- **`#mw:csrf:token`**: `string` - 存储/读取当前会话的 CSRF token

---

#### error(debug) — 异常处理

捕获所有 `\Throwable` 异常。调试模式返回完整堆栈（含 `error`、`file`、`line`、`trace`、`type`），生产模式返回通用错误。

```php
middleware(error(debug: true), $handler);//开发环境，显示完整错误
middleware(error(), $handler);//生产环境，返回通用错误
```

参数：
- **`$debug`**: `bool` 默认 `false` 是否开启调试模式

---

#### gzip(level) — Gzip 响应压缩

检查客户端 `Accept-Encoding: gzip`，压缩后内容比原内容小时启用压缩。自动跳过 `null`、`array`、`OPTIONS` 请求。

```php
middleware(gzip(), $handler);//默认压缩级别 6
middleware(gzip(9), $handler);//最高压缩级别
```

参数：
- **`$level`**: `int` 默认 `6` 压缩级别 1-9

---

#### json(pretty) — JSON 格式化

将返回值转为 JSON 并设置 `Content-Type: application/json; charset=UTF-8`。

```php
middleware(json(), $handler);//默认输出
middleware(json(pretty: true), $handler);//格式化输出
```

参数：
- **`$pretty`**: `bool` 默认 `false` 是否格式化输出

---

#### log(level) — 请求日志

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

#### rate(maxRequests, windowSeconds, key) — 接口限流

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

#### serve(root, map) — 静态文件服务

根据 URI 在指定目录查找静态文件，自动设置 MIME 类型。目录自动追加 `index.html`，启用一年缓存期。

```php
middleware(serve('/var/www/public'), $handler);//基础使用
middleware(serve('/var/www/public', ['html' => 'index.php']), $handler);//扩展名映射
```

参数：
- **`$root`**: `string` 必填 静态文件根目录
- **`$map`**: `array` 默认 `[]` 扩展名到目标文件的映射

内置 MIME 类型支持：`html`、`htm`、`txt`、`css`、`js`、`json`、`png`、`jpg`、`jpeg`、`gif`、`svg`、`ico`、`woff`、`woff2`、`ttf`、`zip`、`xml`

容器配置：
- **`#static:mimes`**: `array` - 扩展 MIME 类型

```php
container('#static:mimes', ['webp' => 'image/webp']);//扩展 MIME 类型
```
