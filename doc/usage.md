# 使用思路与最佳实践

---

## 请求生命周期全景

```
          env() route()  middleware()/hump()/hook()/safe()
           ↓     ↓              ↓
container(set) → [ input() → { 业务 } → output() ]  
                    ↑           ↑             ↑
              from()+filter()   log()        i18n()
                       name() → db()/apcu()/redis() → cache() 
                    
```

**核心路径**就是一次请求最少需要的四个环节。每当你遇到一个新问题，就在对应的环节上选一个扩展来解决：

| 你在哪个环节遇到问题 | 对应的扩展 |
|---|---|
| 配置怎么来 | `env()` — 从环境变量读 |
| 单一路口不够 | `route()` — 多分支路由 |
| 输入需要更细控制 | `from()` + `filter()` 替代 `input()` |
| 业务需要数据库 | `db()` |
| 业务需要缓存 | `apcu()` / `redis()` — 缓存驱动；`cache()` — 编排缓存流程 |
| 业务需要日志 | `log()` |
| 流程需要复杂组织 | `middleware()` / `hump()` / `hook()` |

---

## 核心路径

---

### container()

- 项目的配置中枢和状态容器，所有配置集中写入、按需读取、跨函数共享
- 位于核心路径起点，是每个项目最先做的事
  - 需要从环境变量读取配置？→ 加 `env()`

```php
// config.php — 配置写在单独文件中，一次写入，不逐条 set
return [
    'db'    => ['default' => ['dsn' => 'sqlite:data.db']],
    'cache' => ['file' => __DIR__ . '/data.json', 'ttl' => 3600],
];

container(require __DIR__ . '/config.php');

// 点号自动遍历嵌套，不需要手动取子数组
$dsn = container('db.default.dsn');

// 延迟构建用闭包工厂，首次读取时才执行
container('pdo', fn() => new PDO(container('db.default.dsn')));
$pdo = container('pdo*');
```

---

### input()

- 从 HTTP 请求中安全获取数据，同时完成类型转换和规则验证，一步到位
- 位于核心路径第二步，每个需要接收参数的请求都会用到
  - 需要多分支统一入口？→ 前面加 `route()`
  - 需要对输入做更精细的控制？→ 拆成 `from()` + `filter()`

```php
// 来源和规则组合
$age = input('age', 'query,int,>=18,<=100');

// 多个字段各自带规则
$data = input([
    'id'   => 'int,>0',
    'name' => 'str',
]);

// 路由参数
$id = input('id', 'params', 'int');
```

---

### output()

- 向客户端返回 JSON / 视图 / 文件，框架自动管理 Content-Type、状态码，shutdown 自动发送
- 位于核心路径终点，每个请求最终都通过它返回
- handler 末尾直接调，不需要 return；提前退出时需要 `return` 阻止后续代码

```php
output(['status' => 'ok']);                 // JSON 200
output($data, 201);                          // 指定状态码
return output(['error' => 'msg'], 400);      // 提前退出

output($data, 'template.php');              // 视图
output($data, ['type' => 'file', 'file' => 'photo.jpg']);  // 文件
```

---

## 配置来源扩展

---

### env()

- 不同环境需要不同配置，环境变量不写入代码仓库，是业界标准方案
- 扩展在 `container(set)` 之下，在 `config.php` 中读取后注入容器，不要在业务代码中到处调
- 自动类型转换：`'true'`→`true`，`'false'`→`false`，`'null'`→`null`，`'empty'`→`''`

```php
// config.php — 读取环境变量注入配置
return [
    'debug' => env('APP_DEBUG'),
    'db'    => ['dsn' => env('DB_DSN', 'sqlite:data.db')],
];

// .env 文件路径可指定
container('#env', __DIR__ . '/.env');
```

---

## 输入方式扩展

---

### route()

需要多分支统一入口时使用，根据 method+path 分发到对应 handler。扩展在 `input()` 之上。

**推荐一次性数组形式**，所有路由定义集中在一个数组里，结构一致、易于嵌套。子映射数组中可以混合 int-key callable（中间件）和 string-key 子路由，解析规则：

1. **int-key callable** → 外层视为 `'*'` 通配符（匹配所有路径），内层累积到中间件栈
2. **string-key 子路由** → 截取当前累积栈的快照位置
3. **清单数组 `[fn, fn]`** → 展平后逐个入栈
4. **`['sub' => fn]` assoc 数组** → 视为子映射合并到子路由列表

每个子路由展开时，从累积栈按**位置切片**确定自己的 `before`（切到 pos）和 `after`（pos 切到尾）：

```
                    pos          末尾
[b1, k1:fn1, b2, k2:fn2, a]
├── before(k1) ──┤               k1 → before=stack[:1]=[b1]
├──────── before(k2) ────────┤   k2 → before=stack[:2]=[b1,b2]
                  ├── after(k1) ──┤  → after=stack[1:]=[b2,a]
                                ├─ after(k2) ─┤  → after=stack[2:]=[a]
```

```php
// ✅ 推荐：一次性数组 — API 端点组
route(['api/'=>[
    fn($next) => auth($next),                // before：所有端点先过鉴权
    'post:login'   => fn($next) => output(login(input('name,pass', 'body'))),  // login 跳过 rate_limit
    fn($next) => rate_limit($next),           // login 之后的端点需要限流
    'post:data'    => fn($next) => output(create(input('content', 'body'))),
    'get:list'     => fn($next) => output(fetch(input('page', 'query'))),
    fn($next) => log($next),                 // after：所有端点记日志
]]);
```

每个子路由展开后的实际执行链：

| 子路由 | before | after | 实际链 |
|--------|--------|-------|--------|
| `post:login` | `[auth]` | `[rate_limit, log]` | `auth → login → rate_limit → log` |
| `post:data` | `[auth, rate_limit]` | `[log]` | `auth → rate_limit → data → log` |
| `get:list` | `[auth, rate_limit]` | `[log]` | `auth → rate_limit → list → log` |

#### 多层混写

嵌套时每层独立累积自己的栈，外层 before/after 通过 pending 队列向内透传：

```php
route(['admin/'=>[
    fn($next) => auth($next),                // 外层 before：后台所有路由需鉴权
    'post:login' => fn($next) => output(login(...)),   // login 只过 auth
    'manage/' => [                            // 内层组
        fn($next) => role_check($next, 'admin'),       // 内层 before：manage 下需 admin 角色
        'get:users'  => fn($next) => output(list_users(...)),
        'post:config' => fn($next) => output(set_config(...)),
        fn($next) => op_log($next),                    // 内层 after：manage 操作记操作日志
    ],
    fn($next) => access_log($next),          // 外层 after：所有后台请求记访问日志
]]);
```

内层展开后：

| 路由 | 实际链 |
|------|--------|
| `post:login` | `auth → login → access_log` |
| `get:users` | `auth → role_check → users → op_log → access_log` |
| `post:config` | `auth → role_check → config → op_log → access_log` |

底层使用 `middleware()` 执行最终链，从左到右顺序调用。每个函数接收 `$next`，调之则继续，不调则阻断。

---

### from() 和 filter()

- `input()` 是 `from()` + `filter()` 的高阶整合，当需要对输入做更精细的控制时拆开使用
- `from()` 只负责取，`filter()` 只负责验

```php
// from() — 从指定来源获取未经处理的原始值
$id   = from('id', 'query');      // $_GET
$all  = from(null, 'header');     // 整个来源

// filter() — 类型转换 + 规则验证，失败返回 null
filter('150', 'int,>0,<200');    // 验证+转换
filter('true', 'bool');           // bool true
filter($v, fn($v) => strlen($v) > 2);  // 自定义闭包

// 自定义规则可注册到容器统一管理
container('#filter.phone', [null, null, [fn($v) => preg_match('/^1\d{10}$/', $v)]]);
filter('13800138000', 'phone');
```


---

## 业务能力扩展

---

### db()

- 需要数据库操作时使用，屏蔽 PDO 重复模板，通过 mode 直接拿到需要的数据形态
- 参数始终用 `?` 占位符

```php
$row  = db('SELECT * FROM users WHERE id = ?', [1], 'row');    // 单行
$list = db('SELECT * FROM users', 'list');                       // 列表（无参数时省略绑定位）
$val  = db('SELECT COUNT(*) FROM users', 'value');               // 单值
$id   = db('INSERT INTO users (name) VALUES (?)', ['John'], 'id'); // 自增 ID

// 事务用指令风格
db('BEGIN');
db('UPDATE accounts SET balance = balance - 100 WHERE id = 1');
db('COMMIT');

// 连接配置在 config.php 中完成
// 'db.default' => ['dsn' => 'sqlite:' . __DIR__ . '/data.db'];
db('SELECT * FROM users', 'list', 'default');
```

---

### apcu() 和 redis()

- 业务需要缓存时使用，`apcu()` 是进程内缓存，`redis()` 是跨进程共享缓存
- PHP-FPM 下各进程 APCu 独立，跨进程共享用 `redis()`
- `redis()` 连接失败后后续调用直接返回 null，不会重试

```php
apcu('key');                 // 读
apcu('key', 'value', 60);   // 写 + TTL

redis('key');                // 读
redis('key', 'value', 60);  // 写 + TTL
```

---

### cache()

- 需要简化"查缓存→未命中→回源→回填"流程时使用，是缓存流程的编排者
- 建立在 `apcu()` / `redis()` 之上，通过 middleware 模式组合

```php
$result = cache(
    apcu('key', middleware: 60),    // 第一级 APCu
    redis('key', middleware: 60),   // 第二级 Redis
    fn($next) => db('SELECT ...'),  // 回源，结果自动回填所有级
);
```

---

### log()

- 需要日志记录时使用，统一结构，支持级别和结构化上下文
- 可注入 PSR Logger（如 Monolog）接管输出；未注入时默认 `error_log()` 输出

```php
log('用户 {name} 登录', ['name' => 'admin']);  // 结构化上下文
log('查询超时', 'warning');                     // 指定级别
log(['action' => 'sync', 'count' => 100]);      // 非 string 自动 JSON

// 注入 PSR Logger
container('#log', $monolog);
```

---

### i18n()

- 需要多语言支持时使用，翻译 key 与显示内容分离，面向输出内容
- 翻译通过容器增量设置，key 中的 `.` 自动转 `_`

```php
// 设置语言
i18n(lang: 'en_US');

// 框架内置翻译
$msg = i18n('#error:internal');

// 用户翻译 + 占位符替换
container('i18n.zh_CN.welcome_msg', '欢迎，{name}！');
$msg = i18n('welcome_msg', ['name' => '张三']);
```

---

## 复杂逻辑组织

---

### middleware()

- 需要阻断式管道时使用，每个中间件可决定是否继续
- 不调 `$next` 则终止；`$next` 只有第一次调用有效
- 预置中间件直接传入：cors、auth、token、jwt、rate、csrf 等

```php
// 组合预置中间件
middleware(cors(), auth(), rate(), $handler);

// 自定义中间件 — 认证失败返回 401，不继续
fn ($next) => container('#mw:auth:user')
    ? $next()
    : output(null, 401)
```

---

### hump()

- 需要链式加工管道时使用，不阻断，每一层处理完自动传给下一层
- 不调 `$next` 时自动把当前返回值传给下一层

```php
hump(
    fn($next, $v) => $next($v + 1),
    fn($next, $v) => $next($v * 2),
    1
);  // 4
```

需要阻断能力时用 `middleware()`。

---

### hook()

- 需要在请求结束后自动执行收尾操作时使用（发送响应、记录日志、释放资源）
- 收尾代码不必在每个 handler 里重复

```php
hook(true);                             // 开启，默认序列 ['after', 'end']
hook('after', fn() => output());        // 注册
hook();                                  // 触发默认序列
hook('after', null);                     // 清空指定钩子
hook(null);                              // 清空全部
```

---

## 独立工具

以下函数不与核心路径直接关联：

**name()** — 命名模板工具，统一管理项目中各种 key 的命名规则，可在任意阶段使用

```php
container('#name', ['cache' => ['user' => 'cache:user:{uid}']]);
$key = name('user', ['uid' => 123], 'cache');  // 'cache:user:123'
```

**safe()** — 异常兜底工具，调用可能抛异常但失败可接受时使用，失败返回 null。需要按异常类型区分处理时，通过容器注册错误处理器：

```php
$data = safe(fn() => json_decode($raw, true, 512, JSON_THROW_ON_ERROR));

// 按异常类型区分处理
container('#safe', fn(\Throwable $e) => match(true){
    $e instanceof \PDOException       => fallbackDb(),
    $e instanceof \ValueError         => fallbackValue(),
    $e instanceof \RuntimeException   => null,  // 仍返回 null
    default                           => null,
});
$result = safe(fn() => db('SELECT ...'));
if($result === null && !container('#safe')){
    // 处理器返回 null，说明是默认异常，不需要特殊处理
}
```

**args()** — CLI 参数解析底层，框架内部使用，正常项目中不应直接调用

**test()** — 开发阶段测试验证，与请求流程无关

```php
test('加法', fn() => 2 + 2, 4);
test('范围', 10, fn($v) => $v > 5);
test();      // 执行并输出
test(null);  // 清空
```
