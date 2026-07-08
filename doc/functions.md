
# 函数参考

---

> **目录**
> - [container](#container---容器方法) | [ext](#ext---扩展驱动调用) | [env](#env---环境变量读取) | [key](#key---结构化容器读取底层工具) | [name](#name---完整管道读取) | [args](#args---命令行参数解析) | [safe](#safe---安全调用)
> - 资源连接：[pdo](#pdo---pdo-资源连接池)
> - 输入层：[input](#input---输入数据获取) | [from](#from---从指定来源获取原始值) | [filter](#filter---数据验证与转换)
> - 输出层：[output](#output---输出数据)
> - 流程控制：[route](#route---路由匹配) | [middleware](#middleware---中间件执行引擎) | [hump](#hump---链式中间件执行器) | [hook](#hook---钩子系统)
> - 缓存：[cache](#cache---多级缓存链) | [apcu](#apcu---APCu-缓存驱动)
> - 队列：[queue](#queue---队列操作) | [db](#db---数据库队列驱动)
> - 数据库：[db](#db---数据库操作)
> - HTTP客户端：[http](#http---http-请求)
> - 国际化：[i18n](#i18n---多语言翻译)
> - 开发调试：[log](#log---日志函数) | [test](#test---轻量级测试)

---

**基础设施**

## container - 容器方法

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

## ext - 扩展驱动调用

五种模式统一管理扩展驱动的注册与调用：

```php
ext('queue', 'db', $msg);                 // 1. 单驱 — 调指定 handler
ext('log', true, $level, $msg);           // 2. 广播 — 全部调，收集 [name => result]
ext('log', false, $level, $msg);          // 3. 遍历 — 全部调，不关心返回值
ext('http', null, $method, $url);         // 4. 尝试 — 按序调，首个非 null 返回
ext('http', ['stream', 'curl'], $url);    // 5. 指定序 — 按指定序调，首个非 null 返回
```

通过 `container()` 注册驱动，key 格式 `^#ext.{domain}.{name}`：

```php
// 按条注册（推荐）
container('^#ext.http.curl', \ff\http\curl(...));

// 批量注册
container('^#ext.http', [
    'curl'   => \ff\http\curl(...),
    'stream' => \ff\http\stream(...),
]);
```

---

## env - 环境变量读取

支持系统环境变量、`$_ENV`、`.env` 文件三种来源，自动类型转换（`'true'` → `true`、`'false'` → `false`、`'null'` → `null`、`'empty'` → `''`）。

```php
$host = env('DB_HOST');//不存在返回 null
env('APP_KEY');//读取 .env 文件
```

容器配置：
- **`#env`**: `string` - `.env` 文件路径，默认从 `src/` 向上搜索（最多 3 层）

---

## key - 低层级容器结构化读取

从容器路径读取值，按 `$layer` 选取后原样返回。
`null` 返回 `null`；`array` 按 `$layer` 选取（`null` 取 0 号默认）；其余值原样返回。
广泛适用于翻译、缓存键、环境配置等按层切换的场景。

```php
// 翻译（框架内部使用）
$msg = key('#ff.error.internal');                    // '服务器内部错误'
$msg = key('#ff.error.internal', 'en_US');            // 'Internal server error'

// 缓存键（按驱动选层）
container('^#key', [
    'user' => ['user:{id}', 'redis' => 'u:{id}', 'apcu' => 'uc:{id}'],
]);
$cacheKey = key('#key.user');                         // 'user:{id}'（默认）
$cacheKey = key('#key.user', 'redis');                // 'u:{id}'（redis 层）
$cacheKey = key('#key.user', 'apcu');                 // 'uc:{id}'（apcu 层）

// 环境配置（按部署环境选层）
container('^#cfg', [
    'api_url' => ['https://api.example.com', 'staging' => 'https://staging.api.example.com'],
    'debug'   => ['false', 'dev' => 'true'],
]);
$apiUrl = key('#cfg.api_url', 'staging');             // 'https://staging.api.example.com'
$debug  = key('#cfg.debug', 'dev');                   // 'true'

// 未命中
$val = key('#cfg.not_exists');                        // null
```

**结果规则：**
- `null`：`null`（未命中）
- `array + $layer`：`$entry[$layer] ?? $entry[0] ?? null`
- `array + null layer`：`$entry[0] ?? null`
- 其余值：原样返回

---

## name - 完整管道读取

读 → 选 → 回退 → 替换。通过 `$prefix` 分离容器读取路径与回退模板。

```php
container('^#key', [
    'user'    => ['user:{id}', 'apcu' => 'user_cache:{id}', 'redis' => 'user:{id}'],
    'session' => ['sess:{token}'],
    'article' => 'art:{id}',
]);
// prefix 风格——prefix 拼入读取路径，回退时不含 prefix
$name = name('user', ['id' => 123], prefix: '#key.');      // 'user:123'（0 号默认）
$name = name('user', ['id' => 123], 'apcu', '#key.');      // 'user_cache:123'（指定层）
$name = name('session', ['token' => 'abc'], 'redis', '#key.');// 'sess:abc'（层不存在回退 0 号）
$name = name('token', ['uid' => 5], 'apcu', '#key.');      // 'token'（无 0 号回退 path）
$name = name('un_config', prefix: '#key.');                 // 'un_config'（未配置回退 path）
$name = name('xx:{id}', ['id' => 456], prefix: '#key.');   // 'xx:456'（path 本身作模板）

// 无 prefix——path 即完整容器路径，回退时包含 prefix
$name = name('#key.user', ['id' => 123]);                  // 'user:123'

// 第 2 参字符串简写为 layer
$name = name('user', ['id' => 1], 'apcu', '#key.');        // 'user_cache:1'
```

**内部逻辑：** `key(prefix + path, layer) ?? path` + 占位符替换

容器配置：
- **`#key`**: `array` - 业务键定义，按实体聚合，0 为默认模板，命名键为层覆盖

---

## args - 命令行参数解析

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

## safe - 安全调用

封装 try/catch 模式，失败返回 `null`，省去重复的异常处理模板代码。通过容器注册错误处理器可按异常类型区分响应。

```php
$users = safe(fn() => db('SELECT * FROM users'));//无参调用
$user = safe(fn($id) => db('SELECT * FROM users WHERE id=?', [$id]), 1);//带参数调用
$result = safe(fn($a, $b) => $a / $b, 10, 0);//多参数，返回 null
$data = safe(fn() => json_decode($raw, true, 512, JSON_THROW_ON_ERROR));//异常时静默降级
container('#safe', fn(\Throwable $e) => match(true){//注册异常处理器
    $e instanceof \PDOException       => ['err' => 'db', 'msg' => $e->getMessage()],
    $e instanceof \InvalidArgumentException => ['err' => 'val', 'msg' => $e->getMessage()],
    default                           => null,
});
```

处理器返回 `null` 时仍走"失败返回 null"的默认行为。处理器不影响调用成功的返回值。

---

**资源连接**

## pdo - PDO 资源连接池

按配置名管理 PDO 连接生命周期，支持惰性验证与自动重连。由 `db()` 内部自动调用，也可直接使用。

```php
$pdo = pdo('default');   // 获取/创建默认连接
$pdo = pdo('slave');     // 获取/创建指定配置连接
pdo(null);                // 清空所有连接和失败标记
```

连接通过 `container("#db.{name}")` 配置。默认 `$name = 'default'`，配置缺失或连接失败返回 `null`。

惰性验证：通过 `container('#resource.pdo.ttl')` 设置验证间隔（秒），超时后自动执行 `SELECT 1` 检测连接健康，断连时自动重建。默认 `0` 不验证。

Swoole 自定义工厂：
```php
container('#resource/pdo:', fn($config) => (new \Swoole\Database\PDOPool($config))->get());
$pdo = pdo('default');
```

容器配置：
- **`#db.{name}`**: `array` - PDO 连接配置，支持 `dsn`、`username`、`password`、`options`
- **`#resource.pdo.ttl`**: `int` - 连接验证间隔秒数，默认 `0` 不验证
- **`#resource/pdo:`**: `callable` - 自定义 PDO 工厂，签名 `fn(array $config): \PDO`

---

**输入层**

## input - 输入数据获取

获取输入并验证，组合 `from()` + `filter()`。未指定来源时默认 `body`。

```php
$age = input('age', 'query', 'int', '>=18', '<=100');//单值：来源+规则
$age = input('age', 'body,int,>=18,<=100');//单值：组合规则字符串
$data = input(['id' => 'int,>0', 'name' => 'str']);//批量：map 数组+规则
$list = input(['id', 'name'], 'body');//批量：list 数组+来源
```

---

## from - 从指定来源获取原始值

支持来源：`query` | `cookie` | `file` | `params` | `header` | `input` | `body`。

$name 支持三种形态：
- **string**：单个 key 读取，不存在返回 null
- **list 数组**：批量读取，不存在的 key 返回 null
- **map 数组**：批量读取，value 作为默认值（source 中为 null 时兜底）

```php
$id = from('id', 'body');                                // 单个 key
$name = from('name', 'query');                           // 单个 key
$token = from('authorization', 'header');                // 单个 key
$data = from('id', ['id' => 123, 'name' => 'test']);    // 直接使用数组作为来源
$body = from(null, 'body');                              // 获取整个来源
$result = from(['id', 'name'], 'query');                 // list 批量读取
$data  = from(['id' => 0, 'name' => '?'], 'query');      // map 批量读取 + 默认值兜底
```

容器配置：
- **`#in.input`**: `array` - 预置输入数据（`method`、`uri`、`params`）
- **`#in.params`**: `array` - 预置路由参数
- **`#in.body`**: `array` - 预置请求体
- **`#in.headers`**: `array` - 预置请求头
- **`#in.raw`**: `string` - 预置原始输入
- **`#in.content`**: `array` - 扩展 content-type 解析器

---

## filter - 数据验证与转换

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
container('#filter.phone', [null, null, [fn($v) => preg_match('/^1\d{10}$/', $v)]]);
filter('13800138000', 'phone');//返回 '13800138000'
```

---

**输出层**

## output - 输出数据

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

格式驱动通过 ext 系统注册，内置 `json`/`view`/`file`/`http`。自定义格式注册方式：

```php
// 注册自定义 xml 格式
container('^#ext.output.xml', function(array $response): array {
    $response['headers']['Content-Type'] = 'application/xml';
    $response['body'] = xml_encode($response['body']);
    return $response;
});
output($data, ['type' => 'xml']);
```

容器配置：
- **`#out.default`**: `string` 默认 `'json'` 默认输出格式
- **`#out.emit`**: `callable` 自定义 emit 函数，签名 `fn(array $response): void`
- **`#out.callback`**: `callable` 自定义渲染回调（在 output/http.php 中使用），签名 `fn($response) => ...`

---

**流程控制**

## route - 路由匹配

支持 RESTful 路由、参数占位符、通配符和 CLI 路由。内部使用 `middleware()` 执行匹配到的路由处理函数，支持阻断：不调 `$next` 则终止后续路由。

**返回值：** 匹配成功的路由键数组（`string[]`），未匹配返回 `null`。middleware 执行结果转存到 `#route.result`。

**核心规则：未显式指定的部分从父级继承；顶级路由的隐式父级是 `['*', '/']`**

路由键统一模型 `method:path`：
- 无冒号 → 整个字符串为 path，method 继承父级
- 有冒号 → 左 method、右 path；空侧从父继承
- `''` 或 `':'` → 两侧继承（前缀匹配）

```php
$keys = route('GET:/users', function($next) { output(['users' => []]); });//基础路由，$keys=['GET:/users']
route('GET:/user/{id}', function() { ... });//带参数 {param}
route('POST:/api/user', function() { ... });//POST 路由
route(['get:/api/list' => fn() => 'list', 'post:/api/create' => fn() => 'create']);//路由映射数组
route('GET:/api/*', function() { ... });//通配符路由
route('/bare-path', fn($next) => 'match');//无方法前缀→继承父级(*)，匹配任意方法
route('cli:verbose', function() { ... });//CLI 路由
route(true);//开启延时模式
route();//触发执行收集的路由，返回匹配的 key 数组
route(null);//清空已收集的路由
```

**组前缀展开：** 子映射中的子键自动拼接父路径
```php
route(['get:/root/{root}/game/{id}/'=>[
    'post:run' => fn($next) => ...,    // → get:/root/{root}/game/{id}/run
    'action'   => fn($next) => ...,    // → get:/root/{root}/game/{id}/action（继承父 method）
    ''         => fn($next) => ...,    // → get:/root/{root}/game/{id}/（前缀匹配）
    '*'        => fn($next) => ...,    // → get:/root/{root}/game/{id}/*（通配符）
    ':'        => fn($next) => ...,    // 等效 ''
]]);
```

**智能子路由：** int-key callable 为中间件（累积栈），string-key 为子路由
```php
// 前置中间件 + inline 子路由 + 后置中间件（数组形式）
route(['get:/prefix/'=>[
    fn($next) => ...,                  // 前置（所有子路由之前）
    'post:run' => fn($next) => ...,    // 子路由
    'get:exe'  => fn($next) => ...,    // 子路由
    fn($next) => ...,                  // 后置（所有子路由之后）
]]);
```
也兼容旧格式 `['sub'=>fn]` 包裹子映射：
```php
route('get:/prefix/',
    fn($next) => ...,
    ['post:run' => fn($next) => ...],
    fn($next) => ...,
);
```

中间件按出现位置决定归属：每个子路由获取到它位置为止的累积栈作为 before，之后作为 after。尾部追加给全体 after。
```php
// [b1, k1:fn1, b2, k2:fn2, a]
// k1 → [b1, fn1, b2, a]
// k2 → [b1, b2, fn2, a]
```

清单数组 `[fn, fn]` 自动展平入累积栈：
```php
route(['get:/prefix/'=>[
    fn($next) => ...,                  // 前置
    [fn($next) => ..., fn($next) => ...],// 展平为两个前置
    'run' => fn($next) => ...,         // 子路由
    fn($next) => ...,                  // 后置
]]);
// → run 的 handler 链: [前置, fn1, fn2, handler, 后置]
```

**嵌套子路由：** 子映射支持任意层级嵌套，每层同样支持累积栈机制
```php
// 三层子映射展开
route(['get:/a/'=>['b/'=>['c/'=>['d'=>fn($next)=>...]]]]);
// → get:/a/b/c/d

// 嵌套 + 智能包裹（外层前置/后置向内透传）
route(['get:/level1/'=>[
    fn($next) => basic($next),          // 外层前置
    'level2/' => [                     // 子路由 => 嵌套
        'deep' => fn($next) => output(...),
    ],
    fn($next) => log($next),           // 外层后置
]]);
// → deep: [auth, handler, log]

// 嵌套 + 每层各自的中间件（含展平组）
route(['get:/level1/'=>[
    fn($next) => ...,                  // 外层前置
    'level2/' => [
        [fn($next) => ..., fn($next) => ...],// 内层前置组（展平）
        'deep' => fn($next) => ...,    // 最终 handler
        fn($next) => ...,              // 内层后置
    ],
    fn($next) => ...,                  // 外层后置
]]);
// → deep: [外前置, 内前置A, 内前置B, handler, 内后置, 外后置]
```

**外部文件分块：** 子映射数组可来自 `require`，利用 PHP 自身的文件加载机制拆分路由配置。`require` 返回的数组格式与内联子数组完全一致，自然拼入树结构。
```php
// entry.php — 组装
route([
    'user/' => require 'routes/user.php',   // 子路由组，路径自动拼 user/ 前缀
    'article/' => require 'routes/article.php',
]);

// routes/user.php — 子路由文件，格式与内联相同
return [
    'get:'       => fn() => output(listUsers(...)),
    'post:'      => fn() => output(createUser(...), 201),
    'get:{id}'   => fn() => output(getUser(...)),
    'patch:{id}' => fn() => output(null, updateUser(...) ? 204 : 404),
    'delete:{id}'=> fn() => output(null, deleteUser(...) ? 204 : 404),
];
```
`require` 不是 route() 的特性，是纯 PHP 的文件返回值机制。route() 的 assoc 数组自动识别为子路由映射，无需额外加载逻辑。`...require` spread 可用于将文件内容展平到当前层级（不额外嵌套路径）。

延时执行模式：
```php
route(true);//开启
route('GET:/api/items', fn($next) => output(loadItems()));
route('POST:/api/items', fn($next) => { /* ... */ });
route();//统一触发
```

**$next 规则：** 匹配到多条路由时按顺序执行，调 `$next(...)` 才继续下一条；不调 `$next` 则阻断当前路由链。middleware 最终结果转存 `#route.result`，不随 route() 返回值暴露。

**无 handler 声明：** `route('GET:/path')` 不传函数时，匹配成功仍返回 key 数组（不视为 404），middleware 结果为空。适用于只声明路由存在但不需处理的场景。

多条路由匹配 `*` 通配符阻断示例：
```php
route(['GET:/some/*' => fn($next) => 'wildcard',   // /some/action 匹配时，不调 $next 阻断后续
       'GET:/some/action' => fn($next) => 'action']);// 不会执行
route(['GET:/some/*' => fn($next) => $next(),       // 调 $next 放行
       'GET:/some/action' => fn($next) => 'action']);// 继续执行
```

---

## rest - RESTful 控制器编排

基于 route() 的子路由映射，提供声明式 RESTful 控制器组织。接收 handlers 映射，自动展开为标准 route() 键，配合 input() 规则统一处理请求入参。

```php
$routes = rest([
    'list'    => fn($input) => [users()->list($input)],                   // get:
    'create'  => fn($input) => [users()->create($input)->save(), 201],    // post:
    'get'     => fn($input) => [users()->findByID($input['id'])?->output()], // get:/{id}
    'update'  => fn($input) => [null, users()->findByID($input['id'])?->update($input)?->save() ? 204 : 404], // patch:/{id}
    'replace' => fn($input) => [users()->replace($input['id'], $input)],  // put:/{id}
    'delete'  => fn($input) => [null, users()->findByID($input['id'])?->delete() ? 204 : 404], // delete:/{id}
], '{id}', [
    'list'    => ['name' => 'query,str', 'email' => 'query,email'],
    'create'  => ['name' => 'str', 'email' => 'email'],
    'update'  => ['name' => 'str'],
    'replace' => ['name' => 'str', 'email' => 'email'],
]);
```

**handlers 映射表：**

| key | method | path | 回调签名 | output 行为 |
|---|---|---|---|---|
| `list` | GET | (base) | `fn(array $input): array` | output(...result) |
| `create` | POST | (base) | `fn(array $input): array` | output(...result) |
| `get` | GET | `/{param}` | `fn(array $input): array` | output(...result) |
| `update` | PATCH | `/{param}` | `fn(array $input): array` | output(...result) |
| `replace` | PUT | `/{param}` | `fn(array $input): array` | output(...result) |
| `delete` | DELETE | `/{param}` | `fn(array $input): array` | output(...result) |

**参数说明：**
- **`$handlers`**: `array` — handlers 映射，key 为语义名，value 为业务闭包。闭包返回 `[data]` 或 `[data, code]` 作为 output() 的参数
- **`$param`**: `string` — URL 参数占位符，默认 `'{id}'`，仅 `get/update/replace/delete` 使用
- **`$rules`**: `array` — input 规则映射，key 与 handlers 对应。value 直接传给 `input($value)`，格式遵循 input() 的 map 数组格式（`['field' => 'source,rule', ...]`）

**入参结构：**
- handlers 映射中 `list/create` 不自动提取 URL 参数，入参完全来自 rules 的 input() 结果（rules 为空时 `$input = []`）
- `get/update/replace/delete` 自动提取 `{param}` 到 `$input['paramName']`，再与 rules 的 input() 结果合并（rules 中的同名 key 覆盖自动提取的值，可用于验证）

**返回值：** assoc 数组，格式与 route() 内联子路由完全一致，可直接放入 route() 树结构：

```php
route([
    'user/' => rest([...]),
    'api/'  => require 'routes/api.php',
]);
```

配合 `require` 使用（推荐）：
```php
// routes/user.php
return rest([...], '{id}', [...]);
```

```php
// entry.php
route([
    'user/' => require 'routes/user.php',
]);
```

---

## middleware - 中间件执行引擎

洋葱模型中间件执行器，支持阻断（不调 `$next` 则终止）。参数可以是闭包、文件路径或其他值。

```php
middleware(cors(), basic(), $handler);//执行中间件列表
middleware(fn($next) => $next('value'));//闭包需接收 $next 并调用
middleware('/path/to/file.php', $handler);//文件路径，通过 $next 变量调用下一层
middleware('fallback');//非可调用值作为初始值透传
```

**$next 规则：**
- 中间件按顺序执行，调 `$next(...)` 才继续下一层
- 不调 `$next` 则终止，返回当前中间件的返回值
- 多次调 `$next` 只有第一次生效，后续直接返回第一次的结果

---

## hump - 链式中间件执行器

洋葱模型变体，支持链式调用但不支持阻断。不调 `$next` 也会自动继续执行，并将当前返回值传给下一个。

```php
hump(cors(), basic(), $handler);//执行中间件列表
hump(fn($next) => $next('value'));//不调 $next 自动继续，返回值传给下一层
hump('/path/to/file.php', $handler);//文件路径
```

---

## hook - 钩子系统

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

## cache - 多级缓存链

接收中间件工厂或值，按顺序链式执行。返回 `null` 的中间件自动穿透到下一层。

```php
cache(apcu('key', middleware: true), 'fallback value');//简单兜底
cache(apcu('key', middleware: ['ttl' => 3600]), fn($next) => render());//工厂闭包
cache(apcu('key', middleware: 60), redis('key', middleware: 60), fn($next) => db('SELECT ...'));//多级链
```

---

## apcu - APCu 缓存驱动

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

**队列**

## queue - 队列操作

统一队列入口，支持 Redis list([ff-redis](https://github.com/veasin/ff-redis)) 和数据库（SQLite/MySQL）两种驱动。
第二参数为闭包时进入消费模式，否则为生产模式。

```php
// ---- 生产消息 ----
queue('orders', ['order_id' => 123]);                    // 字符串消息
queue('orders', 'plain text');                          // 字符串消息
queue('orders', $msg, 'email');                          // 指定命名配置
queue('orders', $msg, ['driver' => 'db']);               // 指定内联配置
queue(['q1' => 'm1', 'q2' => 'm2']);                     // 批量生产

// ---- 消费消息 ----
queue('orders', function($msg) {
    return true;        // ack 确认（处理成功）
    return false;       // nack + 重新入队
    return null;        // nack + 丢弃
    return 5;           // 5 秒后重试
});
```

配置：
- **`#queue`**: `array` - 默认队列配置，指定 `driver`（未指定时尝试 `'default'`，未注册则 ext 返回 null）
- **`#queue/db`**: `array` - 数据库驱动配置（`table`、`config`、`db_type`）

---

## db - 数据库队列驱动

基于 PDO 的数据库队列驱动，零新依赖，开箱即用。
自动创建 `ff_queue` 表，支持 SQLite 和 MySQL。

```php
db('orders', ['id' => 1]);                                 // 生产
db('orders', fn($m) => true);                              // 消费
db('orders', fn($m) => true, ['config' => 'queue']);       // 指定 db 连接名
```

生产 INSERT，消费 SELECT + DELETE（类 BRPOP 语义），失败时 re-INSERT。

容器配置：
- **`#queue/db`**: `array` - 配置，支持 `table`（默认 `ff_queue`）、`config`（`db()` 连接名 `#db.{name}`）、`db_type`（`sqlite`/`mysql`）

---

**数据库**

## db - 数据库操作

基于 PDO 的数据库操作，支持多种返回模式、事务和命名配置。

```php
$user = db('SELECT * FROM users WHERE id = ?', [1], 'row');//查询单行
$users = db('SELECT * FROM users', 'list');//查询列表（无参数时省略绑定位）
$count = db('SELECT COUNT(*) FROM users', 'value');//查询单个值
$names = db('SELECT name FROM users', 'column');//查询单列
$pairs = db('SELECT id, name FROM users', 'pairs');//查询键值对
$grouped = db('SELECT status, COUNT(*) FROM users GROUP BY status', 'group');//分组结果
$id = db('INSERT INTO users (name) VALUES (?)', ['John'], 'id');//插入获取 ID
$count = db('UPDATE users SET name = ? WHERE id = ?', ['Jane', 1], 'count');//更新影响行数
$stmt = db('SELECT * FROM users', true);//返回 PDOStatement
$ok = db('INSERT INTO users (name) VALUES (?), (?)', [['John'], ['Jane']], 'ok');//批量插入
$result = db('SELECT * FROM users', fn($stmt, $pdo) => $stmt->fetchAll());//自定义处理
```

事务支持：
```php
db('BEGIN');//开启事务
db('COMMIT');//提交事务
db('ROLLBACK');//回滚事务
db('SAVEPOINT sp1', 'exec');//保存点
db('ROLLBACK TO SAVEPOINT sp1', 'exec');//回滚到保存点
```

$mode 参数按类型分组：

- **string**: `row` 单行数组, `list` 所有行, `value` 单值, `column` 单列, `pairs` 键值对, `group` 分组, `id` 自增ID, `count` 影响行数, `ok` 成功确认, `exec` 多语句执行
- **int**: 自定义 PDO fetch 模式（如 `PDO::FETCH_ASSOC`）
- **true**: 返回 PDOStatement
- **callable**: 自定义处理函数 `fn($stmt, $pdo) => mixed`

exec 模式用于执行多语句 SQL：
```php
db("CREATE TABLE a (id INT); CREATE TABLE b (id INT);", 'exec');//多 DDL
db(file_get_contents('migration.sql'), 'exec', 'migrate');//执行 SQL 脚本文件
```

> MySQL 多语句需在数据库配置的 `options` 中设置 `PDO::MYSQL_ATTR_MULTI_STATEMENTS => true`，否则 `exec()` 会静默忽略除首条外的语句。SQLite 无此限制。

exec 模式返回值：失败返回 `null`，DDL 等无行数返回 `true`，有行数返回 `int`。

容器配置：
- **`#db.{name}`**: `array` - 数据库连接配置，支持 `dsn`、`username`、`password`、`options`

```php
container('#db.default', ['dsn' => 'mysql:host=localhost;dbname=test', 'username' => 'root', 'password' => '']);
$user = db('SELECT * FROM users WHERE id = ?', [1], 'row', 'default');//使用命名配置
```

配合 [ff-sql](https://github.com/veasin/ff-sql) 使用：
```php
$id = db(sql::table('users')->insert(['name' => 'John']), 'id');//插入
$user = db(sql::table('users')->where(['id' => 1])->select(), 'row');//查询
$affected = db(sql::table('users')->where(['id' => 1])->update(['name' => 'Jane']), 'count');//更新
$affected = db(sql::table('users')->where(['id' => 1])->delete(), 'count');//删除
```

---

**HTTP 客户端**

## http - HTTP 请求

HTTP 客户端函数，支持 GET/POST/PUT/PATCH/DELETE/HEAD/OPTIONS。一函数多用：`'METHOD url'` 指定方法与 URL，无空格默认 GET。自动选择 cURL（优先）或 PHP stream 驱动。body 为 array 时按 Content-Type 自动编码，默认 JSON。连接失败返回 `null`。

```php
$data = http('GET https://api.example.com/users');              // GET
$data = http('https://api.example.com/users');                   // 无空格默认 GET
$user = http('POST https://api.example.com/users', ['name' => 'John']); // POST，数组 body 自动 JSON
$user = http('PUT https://api.example.com/users/1', ['name' => 'Jane']); // PUT
$r    = http('PATCH https://api.example.com/users/1', ['age' => 30]);    // PATCH
$r    = http('DELETE https://api.example.com/users/1');                   // DELETE
$r    = http('HEAD https://api.example.com/users');                       // HEAD，body 为空
$r    = http('OPTIONS https://api.example.com/users');                    // OPTIONS
```

**参数：**
- **`$request`**: `string` - `'METHOD url'`，无空格默认 GET
- **`$body`**: `mixed` - 请求体。array 按 Content-Type 编码（默认 JSON）；string 原样发送
- **`$query`**: `?array` - URL 查询参数，自动拼接到 URL
- **`$headers`**: `?array` - 请求头，支持关联 `['Key' => 'val']` 和扁平 `['Key: val']` 两种格式
- **`$option`**: `?array` - 扩展配置，支持 `body`/`query`/`headers` 覆盖、`timeout`、`ssl_verify`、`redirect`、`log`、`encode`、`decode`
- **`$mode`**: `?string` - 返回模式，`null` 完整 | `'body'` | `'code'` | `'ok'` | `'headers'`

```php
// Query 参数
$data = http('GET https://api.example.com/users', query: ['page' => 1, 'limit' => 20]);

// 自定义 Header（关联格式）
$data = http('GET https://api.example.com/users', headers: ['Authorization' => 'Bearer xxx']);

// 自定义 Header（扁平格式）
$data = http('GET https://api.example.com/users', headers: ['Authorization: Bearer xxx']);

// $option 覆盖 body/query/headers
$data = http('POST https://api.example.com/users', option: [
    'body' => ['name' => 'John'],
    'query' => ['debug' => 1],
    'headers' => ['X-Trace: 1'],
]);

// 超时与 SSL
$data = http('GET https://api.example.com/users', option: ['timeout' => 10, 'ssl_verify' => true]);

// 禁止重定向
$data = http('GET https://api.example.com/users', option: ['redirect' => 0]);

// 开启日志（使用框架 log() 函数输出）
$data = http('GET https://api.example.com/users', option: ['log' => true]);

// encode/decode 控制
$data = http('POST https://api.example.com/form', ['a'=>1], option: ['encode'=>'form', 'decode'=>'json']);  // form 发送，json 解码
$data = http('POST https://api.example.com/custom', $data, option: ['encode'=>fn($b,$c)=>my_encode($b)]);   // 自定义编码
$raw = http('GET https://api.example.com/raw', mode:'body', option: ['decode'=>'raw']);                      // 强制原始字符串
```

**返回格式（`$mode = null`）：**

```php
// 完整返回
$r = http('GET https://api.example.com/users');
// ['body' => [...], 'code' => 200, 'headers' => [...], 'message' => '']

// 模式裁剪
$body    = http('GET https://api.example.com/users', mode: 'body');    // 只返回 body
$code    = http('GET https://api.example.com/users', mode: 'code');    // 只返回状态码
$ok      = http('GET https://api.example.com/users', mode: 'ok');      // true (2xx) / false
$headers = http('GET https://api.example.com/users', mode: 'headers'); // 只返回响应头
```

**encode/decode 控制：**

通过 `option.encode` 和 `option.decode` 控制请求体编码和响应解码。未设 `decode` 时默认同 `encode`（便于对称场景）。

**encode（请求体编码，仅对 array/iterable body 生效）：**

| 值 | 行为 |
|---|---|
| `null`（默认） | 按已有 Content-Type 判断：urlencoded→`http_build_query`，其他或无→`json_encode`，无 CT 时自动添加 |
| `'json'` | 强制 `json_encode`，无 CT 时自动添加 |
| `'form'` | 强制 `http_build_query`，无 CT 时自动添加 `Content-Type: application/x-www-form-urlencoded` |
| `Closure` | `fn(mixed $body, 'encode'): ?string` 自定义编码，返回 null 时不发送 body |

自动添加的 Content-Type 不会覆盖已存在的 header。

**decode（响应解码，对 `mode=null` 和 `mode='body'` 生效）：**

| 值 | 行为 |
|---|---|
| `null`（默认） | 按 Content-Type 自动：json→`json_decode`，urlencoded→`parse_str`，`#in.content` 扩展解析器（与 `from()` 共享），无匹配时保持原始 |
| `'json'` | 强制 `json_decode` |
| `'form'` | 强制 `parse_str` |
| `'raw'` | 保持原始字符串，不解码 |
| `Closure` | `fn(string $raw, 'decode'): mixed` 自定义解码 |

**容器配置：**

```php
// 全局 HTTP 配置（driver: null=尝试 curl→stream, 'curl'=强制, ['stream','curl']=指定序）
container('#http', ['timeout' => 10, 'ssl_verify' => false, 'driver' => 'curl']);

// 驱动特定配置（cURL 优先于全局）
container('#http/curl', ['timeout' => 5, 'redirect' => 3]);

// PHP stream 配置
container('#http/stream', ['timeout' => 15]);
```

驱动通过 ext 系统注册，内置 curl/stream。`driver` 值决定模式：

| `driver` 值 | 模式 | 行为 |
|---|---|---|
| `null`（默认） | 尝试 | curl → stream，首个非 null 返回 |
| `'curl'` | 单驱 | 只调 curl |
| `'stream'` | 单驱 | 只调 stream |
| `['stream','curl']` | 指定序 | 按序调，首个非 null 返回 |
| `'custom'` | 单驱 | 自行注册 `^#ext.http.custom` |

配置优先级：`$option` 调用时传参 > `#http/curl`/`#http/stream` 驱动配置 > `#http` 全局配置。

**失败处理：** 连接超时、DNS 解析失败、SSL 握手失败等网络错误统一返回 `null`，通过 `mode: 'ok'` 或 `=== null` 判断。

```php
$r = http('GET https://api.example.com/users', mode: 'ok') or log('请求失败');
if(($data = http('GET https://api.example.com/users')) === null) log('网络错误');
```

---

**国际化**

## i18n - 多语言翻译

支持占位符替换和强制语言，框架翻译键格式 `#ff.{模块}.{key}`，`.` 为层级分隔符。

```php
i18n(lang: 'en_US');                                         // 设置当前语言
$msg = i18n('#ff.error.internal');                           // 框架翻译
$msg = i18n('#ff.error.internal', 'en_US');                  // 强制语言
$msg = i18n('{message}', ['message' => 'error msg']);         // 无翻译时，{message} 替换为 'error msg'
$msg = i18n('myapp.welcome', ['name' => '张三']);             // {name} 占位符替换
$msg = i18n('myapp.welcome', ['name' => 'Alice'], 'en_US');   // 强制语言 + 占位符
```

容器配置：
- **`i18n.{ns}.{path}`**: `string|array` - 用户翻译值，string 所有语言相同，array 格式 `[default, lang => localized]`
- **`^i18n.lang`**: `string` - 持久化当前语言

```php
// 按命名空间一次性写入
container('^i18n.myapp', [
    'welcome' => ['欢迎', 'en_US' => 'Welcome'],
]);
// 请求级单键覆盖
container('i18n.#ff.error.internal', '自定义错误');
container('^i18n.lang', 'en_US');
```

---

**开发调试**

## log - 日志函数

广播到所有 ext 注册的 handler。默认无任何驱动，需自行注册 handler：

```php
log('用户登录');                                    // 默认 level info
log('发生错误', 'error');                           // 指定 level
log('用户 {name} 登录', ['name' => 'admin']);        // {key} 占位符替换
log('错误: {msg}', ['msg' => '连接失败'], 'error');  // context + level
log(['a' => 1, 'b' => 2]);                          // 非 string 消息自动 json
log(new StringableClass());                          // 支持 Stringable 对象
```

通过 ext 注册驱动，key 格式 `^#ext.log.{name}`：

```php
// 注册 error_log 驱动（含 {placeholder} 替换）
container('^#ext.log.error', \ff\log\error(...));

// 多个 handler 同时接收广播
container('^#ext.log.file', fn($level, $message, $context) => file_put_contents('app.log', "[$level] $message\n", FILE_APPEND));
```

---

## test - 轻量级测试

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
