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

配置读取、设置与延迟构建。

```php
// 获取所有配置
$all = container();

// 清空配置
container(null);

// 检查键是否存在（支持 . 分隔）
$exists = container(null, 'database.host');  // 返回 bool

// 读取值（支持 . 分隔）
$host = container('database.host');

// 设置值
container('database.host', 'localhost');
container('app.debug', true);

// 删除键（设置 null）
container('database.host', null);

// 批量读取（list 数组）
$values = container(['database.host', 'app.debug']);  // 返回对应的值数组

// 批量设置（map 数组）
container([
    'database.host' => '127.0.0.1',
    'database.port' => 3306,
]);

// 延迟构建
container('version', fn() => file_get_contents('version.txt'));
$version = container('version');  // 访问时自动执行
```

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
container('nx:method', 'put');
```

> 缓存键：`nx:method`

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
container('nx:from:input', ['method' => 'get', 'uri' => '/test', 'params' => []]);
container('nx:from:params', ['id' => 123]);  // 预置路由参数
container('nx:from:body', ['name' => 'test']);  // 预置请求体
container('nx:from:headers', ['Authorization' => 'Bearer xxx']);  // 预置请求头

// 扩展 content-type 解析器
container('nx:from:content', [
    'application/xml' => fn($raw) => simplexml_load_string($raw),
    'default' => fn($raw) => ['raw' => $raw],
]);
```

> 缓存键：`nx:from:input`、`nx:from:params`、`nx:from:body`、`nx:from:headers`、`nx:from:raw`、`nx:from:content`

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

// 扩展规则（通过容器配置 nx:filter）
container('nx:filter', [
    'phone' => [null, null, [fn($v) => preg_match('/^1\d{10}$/', $v)]],
]);
filter('13800138000', 'phone');  // 返回 '13800138000'
```

> 扩展方式：`container('nx:filter', [...])`

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

// 扩展输出格式（通过容器配置 nx:output:formats）
container('nx:output:formats', [
    'xml' => function($response, $formats) {
        $response['headers']['Content-Type'] = 'application/xml';
        $response['body'] = xml_encode($response['body']);
        $formats['http']($response, $formats);
    },
]);
output($data, 'xml');

// 自定义渲染回调
container('nx:output:callback', function($response) {
    echo json_encode($response['body']);
});
```

> 扩展方式：`container('nx:output:formats', [...])`  
> 回调方式：`container('nx:output:callback', fn($response) => ...)`

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

#### middleware - 中间件执行（洋葱模型）

```php
// 基础用法
$result = middleware(
    function($next) {
        echo "Before\n";
        $result = $next();
        echo "After\n";
        return $result;
    },
    function($next, $value) {
        return $value + 1;
    },
    5  // 初始值
);

// 日志中间件
$log = fn($next, ...$args) => tap($next(...$args), fn($r) => log($r));

// 链式执行
$result = middleware($auth, $validation, $handler, $response);
```

#### run - 执行中间件链

```php
// 简单链式调用
$result = run(
    fn($v) => $v + 1,
    fn($v) => $v * 2,
    5  // 初始值
);
// 结果: 12 ( (5+1)*2 )

// 条件执行
$result = run(
    $condition ? $middleware1 : null,
    $handler
);
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
container('nx:log', $psrLogger);

// 注入闭包（fn 不会被容器自动执行）
container('nx:log.fn', fn($level, $message, $context) => ...);
```

> PSR Logger 方式：`container('nx:log', $logger)`  
> 闭包方式：`container('nx:log.fn', fn(...) => ...)`
```
