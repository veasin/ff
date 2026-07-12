<?php
require __DIR__ . '/../vendor/autoload.php';

use function ff\{container, input, test};

// ============================================================
// 测试数据准备
// ============================================================
$_GET = ['name' => 'test', 'age' => '25', 'id' => '123', 'price' => '9.9', 'flag' => '1'];
$_POST = ['id' => '456', 'name' => 'John', 'email' => 'john@example.com', 'title' => '', 'bio' => null, 'code' => '0', 'num' => '0', 'ip' => '192.168.1.1', 'price_str' => '9.9'];
container('#in.body', $_POST);

// ============================================================
// 1. 基础类型转换
// ============================================================
test('str 类型', input(['name' => ['str']], ['from' => 'query']), ['name' => 'test']);
test('int 类型', input(['age' => ['int']], ['from' => 'query']), ['age' => 25]);
test('int 非数字返回 null', input(['name' => ['int']], ['from' => 'query']), ['name' => null]);
test('float 类型', input(['price' => ['float']], ['from' => 'query']), ['price' => 9.9]);
test('bool 类型', input(['flag' => ['bool']], ['from' => 'query']), ['flag' => true]);

// ============================================================
// 2. 来源指定
// ============================================================
test('from body', input(['id' => ['int', 'body']]), ['id' => 456]);
test('from query', input(['name' => ['str', 'query']]), ['name' => 'test']);
test('默认来源 body', input(['name' => ['str']]), ['name' => 'John']);

// ============================================================
// 3. 逗号分隔简写
// ============================================================
test('str,query', input(['name' => 'str,query']), ['name' => 'test']);
test('int,body', input(['id' => 'int,body']), ['id' => 456]);

// ============================================================
// 4. key:value 格式
// ============================================================
test('from:query', input(['name' => 'str,from:query']), ['name' => 'test']);
test('null:0', input(['missing' => 'str,null:0']), ['missing' => '0']);
test('null:remove', input(['missing' => 'str,null:remove']), []);
test('null:throw', function(){
	input(['missing' => 'str,null:throw']);
}, fn($v) => $v instanceof \RuntimeException);

// ============================================================
// 5. abbr 缩写
// ============================================================
test('integer → int', input(['age' => ['integer']], ['from' => 'query']), ['age' => 25]);
test('body → from:body', input(['name' => ['str', 'body']]), ['name' => 'John']);
test('query → from:query', input(['name' => ['str', 'query']]), ['name' => 'test']);
test('remove → null:remove', input(['missing' => ['str', 'remove']]), []);
test('throw → null:throw', function(){
	input(['missing' => ['str', 'throw']]);
}, fn($v) => $v instanceof \RuntimeException);

// ============================================================
// 6. null 处理
// ============================================================
test('null 默认行为（str 将 null 转为 ""）', input(['missing' => ['str']]), ['missing' => '']);
test('null:remove', input(['missing' => ['str', 'null' => 'remove']]), []);
test('null:throw', function(){
	input(['missing' => ['str', 'null' => 'throw']]);
}, fn($v) => $v instanceof \RuntimeException);
test('null 默认值', input(['missing' => ['str', 'null' => 'default', 'default' => 'default']]), ['missing' => 'default']);
test('null 简写值', input(['missing' => ['null' => 0]]), ['missing' => 0]);
test('null 简写字符串', input(['missing' => ['null' => 'N/A']]), ['missing' => 'N/A']);

// ============================================================
// 7. empty 处理
// ============================================================
test('empty 默认行为（保留空值）', input(['title' => ['str']]), ['title' => '']);
test('empty:remove', input(['title' => ['str', 'empty' => 'remove']]), []);
test('empty:throw', function(){
	input(['title' => ['str', 'empty' => 'throw']]);
}, fn($v) => $v instanceof \RuntimeException);
test('empty 默认值', input(['title' => ['str', 'empty' => 'default', 'default' => 'default']]), ['title' => 'default']);
test('empty 简写值', input(['title' => ['str', 'empty' => 'N/A']]), ['title' => 'N/A']);

// ============================================================
// 8. defaults 继承与覆盖
// ============================================================
test('defaults from 继承', input(['name' => ['str']], ['from' => 'query']), ['name' => 'test']);
test('defaults null 继承', input(['missing' => ['str']], ['null' => 'fallback']), ['missing' => 'fallback']);
test('field 覆盖 defaults from', input(['name' => ['str', 'body']], ['from' => 'query']), ['name' => 'John']);
test('field 覆盖 defaults null', input(['missing' => ['str', 'null' => 'X']], ['null' => 'Y']), ['missing' => 'X']);

// ============================================================
// 9. 多字段批量
// ============================================================
test('多字段', input([
	'name'  => ['str', 'query'],
	'age'   => ['int', 'query'],
	'email' => ['str', 'body'],
], []), ['name' => 'test', 'age' => 25, 'email' => 'john@example.com']);

// ============================================================
// 10. 检查规则（单参数）
// ============================================================
test('email 通过', input(['email' => ['str', 'email']], ['from' => 'body']), ['email' => 'john@example.com']);
test('email 失败', function(){
	input(['x' => ['str', 'email']], ['from' => 'body']);
}, fn($v) => $v instanceof \RuntimeException);

// ============================================================
// 11. 闭包检查
// ============================================================
test('闭包通过', input(['age' => ['int', fn($v) => $v > 0]], ['from' => 'query']), ['age' => 25]);
test('闭包失败', function(){
	input(['age' => ['int', fn($v) => $v > 100]], ['from' => 'query']);
}, fn($v) => $v instanceof \RuntimeException);

// ============================================================
// 12. error 自定义错误
// ============================================================
test('null throw 自定义错误', function(){
	input(['missing' => ['str', 'null' => 'throw', 'error' => '名称必填']]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '名称必填';
});
test('empty throw 自定义错误', function(){
	input(['title' => ['str', 'empty' => 'throw', 'error' => '标题不能为空']]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '标题不能为空';
});

// ============================================================
// 13. 未知规则抛异常
// ============================================================
test('未知规则', function(){
	input(['x' => ['unknown_rule']]);
}, fn($v) => $v instanceof \InvalidArgumentException);

// ============================================================
// 14. 命名参数格式
// ============================================================
test('命名 from', input(['name' => ['str', 'from' => 'query']]), ['name' => 'test']);
test('命名 null', input(['x' => ['str', 'null' => 'fallback']]), ['x' => 'fallback']);
test('命名 error 数组', function(){
	input(['x' => ['str', 'null' => 'throw', 'error' => ['message' => '自定义', 'code' => 42]]]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '自定义' && $v->getCode() === 42;
});

// ============================================================
// 15. 逗号分隔 + 命名混合
// ============================================================
test('逗号混合命名', input(['name' => 'str,null:0,from:query']), ['name' => 'test']);

// ============================================================
// 16. defaults 字符串格式
// ============================================================
test('defaults 字符串', input(['name' => ['str']], ['from' => 'query']), ['name' => 'test']);

// ============================================================
// 17. 值为 '0' 遵循 PHP empty() 语义
// ============================================================
test('字符串 0 触发 empty', input(['code' => ['str', 'null' => '0', 'empty' => 'remove']]), []);
test('int 0 触发 empty', input(['num' => ['int', 'null' => 0, 'empty' => 'remove']]), []);

// ============================================================
// 18. key 规则（字段名 ≠ lookup key）
// ============================================================
test('key 规则', input(['x' => ['str', 'key' => 'name']]), ['x' => 'John']);
test('key 规则 + from:query', input(['x' => ['str', 'key' => 'name', 'from' => 'query']]), ['x' => 'test']);
test('key 逗号简写', input(['x' => 'str,key:name']), ['x' => 'John']);

// ============================================================
// 19. from 闭包
// ============================================================
test('from 闭包', input(['x' => ['str', 'from' => fn($k) => 'cached_' . $k]]), ['x' => 'cached_x']);

// ============================================================
// 20. from ArrayAccess
// ============================================================
test('from ArrayAccess', input(['id' => ['int', 'from' => new \ArrayObject(['id' => '789'])]]), ['id' => 789]);

// ============================================================
// 21. check 返回 ['pass'|'throw'|'default'|'remove', $opts]
// ============================================================
test('check 返回 pass', input(['age' => ['int', fn($v) => ['pass', []]]], ['from' => 'query']), ['age' => 25]);
test('check 返回 throw', function(){
	input(['age' => ['int', fn($v) => ['throw', ['message' => 'too young']]]], ['from' => 'query']);
}, function($v){ return $v instanceof \RuntimeException && $v->getMessage() === 'too young'; });
test('check 返回 default', input(['age' => ['int', fn($v) => $v > 100 ? ['pass', []] : ['default', ['value' => 99]]]], ['from' => 'query']), ['age' => 99]);
test('check 返回 remove', input(['age' => ['int', fn($v) => ['remove', []]]], ['from' => 'query']), []);
test('check 返回 false+fail:default', input(['age' => ['int', fn($v) => ['pass', ['fail' => 'default', 'value' => 0]]]], ['from' => 'query']), ['age' => 25]);
test('check 返回 false+fail:remove', input(['age' => ['int', fn($v) => [false, ['fail' => 'remove']]]], ['from' => 'query']), []);

// ============================================================
// 22. error.exception 配置异常类
// ============================================================
test('自定义异常类', function(){
	input(['x' => ['str', 'null' => 'throw', 'error' => ['message' => 'fail', 'exception' => \InvalidArgumentException::class]]]);
}, fn($v) => $v instanceof \InvalidArgumentException);
test('默认异常类是 RuntimeException', function(){
	input(['x' => ['str', 'null' => 'throw']]);
}, fn($v) => $v instanceof \RuntimeException && !($v instanceof \InvalidArgumentException));

// ============================================================
// 23. bootstrap 注册 check: ip-v4, ip-v6, number
// ============================================================
test('ip-v4 通过', input(['ip' => ['str', 'ip-v4']]), ['ip' => '192.168.1.1']);
test('ip-v4 失败', function(){
	input(['ip' => ['str', 'ip-v4']], ['from' => fn($k) => 'not-an-ip']);
}, fn($v) => $v instanceof \RuntimeException);
test('ip-v6 通过', input(['ip' => ['str', 'ip-v6']], ['from' => fn($k) => '::1']), ['ip' => '::1']);
test('ip-v6 失败', function(){
	input(['ip' => ['str', 'ip-v6']], ['from' => fn($k) => 'not-an-ip']);
}, fn($v) => $v instanceof \RuntimeException);
test('number 通过', input(['price_str' => ['str', 'number']]), ['price_str' => '9.9']);
test('number 失败', function(){
	input(['name' => ['str', 'number']]);
}, fn($v) => $v instanceof \RuntimeException);

// ============================================================
// 24. null/empty 完整格式 [fail, default, error]
// ============================================================
test('null 完整格式 fail:remove', input(['missing' => ['str', 'null' => ['fail' => 'remove']]]), []);
test('null 完整格式 fail:throw+error', function(){
	input(['missing' => ['str', 'null' => ['fail' => 'throw', 'error' => ['message' => '必填', 'code' => 400]]]]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '必填' && $v->getCode() === 400;
});
test('null 完整格式 fail:default', input(['missing' => ['str', 'null' => ['fail' => 'default', 'default' => 'N/A']]]), ['missing' => 'N/A']);
test('empty 完整格式 fail:remove', input(['title' => ['str', 'empty' => ['fail' => 'remove']]]), []);
test('empty 完整格式 fail:throw+error', function(){
	input(['title' => ['str', 'empty' => ['fail' => 'throw', 'error' => ['message' => '不能为空', 'code' => 422]]]]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '不能为空' && $v->getCode() === 422;
});
test('empty 完整格式 fail:default', input(['title' => ['str', 'empty' => ['fail' => 'default', 'default' => 'N/A']]]), ['title' => 'N/A']);

// ============================================================
// 25. default 规则（null/empty fail=default 时回退取值）
// ============================================================
test('null fail=default 读取字段 default', input(['x' => ['str', 'null' => 'default', 'default' => 'fb']]), ['x' => 'fb']);
test('empty fail=default 读取字段 default', input(['x' => ['str', 'empty' => 'default', 'default' => 'fb']]), ['x' => 'fb']);
test('null 完整格式 fail=default 读取字段 default', input(['x' => ['str', 'null' => ['fail' => 'default'], 'default' => 'fb']]), ['x' => 'fb']);

// ============================================================
// 26. error 继承（null/empty 的 error 继承字段 error）
// ============================================================
test('null throw 继承 error', function(){
	input(['x' => ['str', 'null' => 'throw', 'error' => ['message' => '字段必填', 'code' => 100]]]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '字段必填' && $v->getCode() === 100;
});
test('null error 覆盖字段 error', function(){
	input(['x' => ['str', 'null' => ['fail' => 'throw', 'error' => ['message' => '局部错误']], 'error' => ['message' => '全局错误']]]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '局部错误';
});

// ============================================================
// 27. error 简写格式（int/string）
// ============================================================
test('error 整数 code', function(){
	input(['x' => ['str', 'null' => 'throw', 'error' => 500]]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getCode() === 500;
});
test('error 字符串 message', function(){
	input(['x' => ['str', 'null' => 'throw', 'error' => '出错了']]);
}, function($v){
	return $v instanceof \RuntimeException && $v->getMessage() === '出错了';
});

test();
