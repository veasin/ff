<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, filter, test};

// ============================================================
// 1. 基础类型转换
// ============================================================
test('str 类型', filter('test', 'str'), 'test');
test('int 类型', filter('25', 'int'), 25);
test('int 非数字返回 null', filter('abc', 'int'), null);
test('float 类型', filter('9.9', 'float'), 9.9);
test('float 非数字返回 null', filter('abc', 'float'), null);
test('bool 类型', filter('1', 'bool'), true);
test('bool 0', filter('0', 'bool'), false);

// ============================================================
// 2. abbr 缩写
// ============================================================
test('email → filter', filter('test@example.com', 'email'), 'test@example.com');
test('uuid → regex', filter('550e8400-e29b-41d4-a716-446655440000', 'uuid'), '550e8400-e29b-41d4-a716-446655440000');

// ============================================================
// 3. empty 处理
// ============================================================
test('empty 默认行为（保留空值）', filter('', 'str'), '');
test('empty:remove', filter('', ['str', 'empty' => 'remove']), null);
test('empty:throw', filter('', ['str', 'empty' => 'throw']), null);
test('empty 默认值', filter('', ['str', 'empty' => 'default', 'default' => 'N/A']), 'N/A');
test('empty 简写值', filter('', ['str', 'empty' => 'N/A']), 'N/A');
test('empty 完整格式 fail:default', filter('', ['str', 'empty' => ['fail' => 'default', 'default' => 'fallback']]), 'fallback');

// ============================================================
// 4. check 规则（单参数）
// ============================================================
test('email 通过', filter('test@example.com', ['str', 'email']), 'test@example.com');
test('email 失败', filter('bad', ['str', 'email']), null);
test('number 通过', filter('9.9', ['str', 'number']), '9.9');
test('number 失败', filter('abc', ['str', 'number']), null);
test('ip 通过', filter('192.168.1.1', ['str', 'ip']), '192.168.1.1');
test('ip 失败', filter('not-ip', ['str', 'ip']), null);
test('ip-v6 通过', filter('::1', ['str', 'ip-v6']), '::1');
test('ip-v6 失败', filter('not-ip', ['str', 'ip-v6']), null);

// ============================================================
// 5. cmp check（单参数）
// ============================================================
test('cmp >=18 通过', filter(20, ['int', 'cmp' => ['>=', 18]]), 20);
test('cmp >=18 失败', filter(15, ['int', 'cmp' => ['>=', 18]]), null);
test('cmp <100 通过', filter(50, ['int', 'cmp' => ['<', 100]]), 50);
test('cmp =0 通过', filter(0, ['int', 'cmp' => ['=', 0]]), 0);
test('cmp =0 失败', filter(1, ['int', 'cmp' => ['=', 0]]), null);
test('cmp 字符串长度通过', filter('hello', ['str', 'cmp' => ['>=', 3]]), 'hello');
test('cmp 字符串长度失败', filter('hi', ['str', 'cmp' => ['>=', 3]]), null);
test('cmp 未知操作符', filter(10, ['int', 'cmp' => ['<>', 5]]), null);

// ============================================================
// 5.1 range check（双参数）
// ============================================================
test('range 数字通过', filter(50, ['int', 'range' => [1, 100]]), 50);
test('range 数字太小', filter(0, ['int', 'range' => [1, 100]]), null);
test('range 数字太大', filter(101, ['int', 'range' => [1, 100]]), null);
test('range 数字边界', filter(1, ['int', 'range' => [1, 100]]), 1);
test('range 字符串长度通过', filter('hello', ['str', 'range' => [3, 12]]), 'hello');
test('range 字符串长度太短', filter('hi', ['str', 'range' => [3, 12]]), null);
test('range 字符串长度太长', filter('hello world!!!', ['str', 'range' => [3, 12]]), null);

// ============================================================
// 6. 闭包检查
// ============================================================
test('闭包通过', filter(25, ['int', fn($v) => $v > 0]), 25);
test('闭包失败', filter(25, ['int', fn($v) => $v > 100]), null);
test('闭包通过 字符串', filter('hello', fn($v) => strlen($v) > 3), 'hello');
test('闭包失败 字符串', filter('hi', fn($v) => strlen($v) > 3), null);

// ============================================================
// 7. check 返回 ['pass'|'throw'|'default'|'remove', $opts]
// ============================================================
test('check 返回 pass', filter(25, ['int', fn($v) => ['pass', []]]), 25);
test('check 返回 throw', filter(25, ['int', fn($v) => ['throw', ['message' => 'too young']]]), null);
test('check 返回 default', filter(25, ['int', fn($v) => $v > 100 ? ['pass', []] : ['default', ['value' => 99]]]), null);
test('check 返回 remove', filter(25, ['int', fn($v) => ['remove', []]]), null);
test('check 返回 false+fail:default', filter(25, ['int', fn($v) => ['pass', ['fail' => 'default', 'value' => 0]]]), 25);
test('check 返回 false+fail:remove', filter(25, ['int', fn($v) => [false, ['fail' => 'remove']]]), null);

// ============================================================
// 8. 空值类型
// ============================================================
test('空字符串 str 通过', filter('', 'str'), '');
test('空字符串 int 返回 null', filter('', 'int'), null);
test('值为 0 触发 empty', filter(0, ['int', 'empty' => 'remove']), null);

// ============================================================
// 9. 未知规则抛异常
// ============================================================
test('未知规则', function(){
	filter('test', 'unknown_rule');
}, fn($v) => $v instanceof \InvalidArgumentException);

// ============================================================
// 10. filter 不支持的 input 规则
// ============================================================
test('from 规则报错', function(){
	filter('test', ['str', 'from' => 'query']);
}, fn($v) => $v instanceof \InvalidArgumentException);
test('null 规则报错', function(){
	filter('test', ['str', 'null' => 'default']);
}, fn($v) => $v instanceof \InvalidArgumentException);
test('key 规则报错', function(){
	filter('test', ['str', 'key' => 'name']);
}, fn($v) => $v instanceof \InvalidArgumentException);

// ============================================================
// 11. 组合规则
// ============================================================
test('类型+check', filter('5', ['int', 'cmp' => ['>=', 0]]), 5);
test('类型+check 失败', filter('5', ['int', 'cmp' => ['>=', 10]]), null);
test('类型+空值+check', filter('', ['str', 'empty' => 'default', 'default' => 'N/A']), 'N/A');

// ============================================================
// 12. parse 简写规则
// ============================================================
test('>18 通过', filter(20, ['int', '>18']), 20);
test('>18 失败', filter(15, ['int', '>18']), null);
test('>=0 通过', filter(5, ['int', '>=0']), 5);
test('>=0 0通过', filter(0, ['int', '>=0']), 0);
test('<100 通过', filter(50, ['int', '<100']), 50);
test('<100 失败', filter(150, ['int', '<100']), null);
test('<=100 通过', filter(100, ['int', '<=100']), 100);
test('=0 通过', filter(0, ['int', '=0']), 0);
test('=0 失败', filter(1, ['int', '=0']), null);
test('!=0 通过', filter(5, ['int', '!=0']), 5);
test('!=0 失败', filter(0, ['int', '!=0']), null);
test('>18 逗号简写', filter(20, 'int,>18'), 20);
test('>18.5 浮点通过', filter(19.0, ['float', '>18.5']), 19.0);
test('>18.5 浮点失败', filter(18.0, ['float', '>18.5']), null);
test('>=3 字符串长度通过', filter('hello', ['str', '>=3']), 'hello');
test('>=3 字符串长度失败', filter('hi', ['str', '>=3']), null);

// ============================================================
// 13. enum check
// ============================================================
test('enum 通过', filter('aa', ['str', 'enum' => ['aa', 'bb', 'cc']]), 'aa');
test('enum 失败', filter('dd', ['str', 'enum' => ['aa', 'bb', 'cc']]), null);
test('enum 数字字符串', filter('2', ['str', 'enum' => ['1', '2', '3']]), '2');
test('enum 数字不匹配', filter(2, ['enum' => ['1', '2', '3']]), null);

// ============================================================
// 14. uuid check
// ============================================================
test('uuid 通过', filter('550e8400-e29b-41d4-a716-446655440000', ['str', 'uuid']), '550e8400-e29b-41d4-a716-446655440000');
test('uuid 失败', filter('not-a-uuid', ['str', 'uuid']), null);
test('uuid abbr', filter('550e8400-e29b-41d4-a716-446655440000', 'str,uuid'), '550e8400-e29b-41d4-a716-446655440000');

// ============================================================
// 15. parse 枚举简写
// ============================================================
test('parse 枚举通过', filter('aa', ['str', 'aa|bb|cc']), 'aa');
test('parse 枚举失败', filter('dd', ['str', 'aa|bb|cc']), null);
test('parse 枚举数字', filter('2', ['str', '1|2|3']), '2');
test('parse 枚举逗号简写', filter('aa', 'str,aa|bb|cc'), 'aa');

// ============================================================
// 16. regex check
// ============================================================
test('regex 通过', filter('hello', ['str', 'regex' => '/^[a-z]+$/']), 'hello');
test('regex 失败', filter('Hello', ['str', 'regex' => '/^[a-z]+$/']), null);
test('regex 大小写', filter('Hello', ['str', 'regex' => '/^[a-z]+$/i']), 'Hello');
test('regex uuid abbr', filter('550e8400-e29b-41d4-a716-446655440000', ['str', 'uuid']), '550e8400-e29b-41d4-a716-446655440000');
test('regex uuid 失败', filter('not-a-uuid', ['str', 'uuid']), null);

// ============================================================
// 17. regex parse 简写
// ============================================================
test('regex: 通过', filter('hello', ['str', 'regex:/^[a-z]+$/']), 'hello');
test('regex: 失败', filter('Hello', ['str', 'regex:/^[a-z]+$/']), null);

// ============================================================
// 18. 范围 parse 简写（数值范围）
// ============================================================
test('1..100 通过', filter(50, '1..100'), 50);
test('1..100 0失败', filter(0, '1..100'), null);
test('1..100 超出', filter(101, '1..100'), null);

// ============================================================
// 19. 长度范围 parse 简写
// ============================================================
test('3-12 通过', filter('hello', '3-12'), 'hello');
test('3-12 太短', filter('hi', '3-12'), null);
test('3-12 太长', filter('hello world!!!', '3-12'), null);
test('3-12 逗号简写', filter('hello', 'str,3-12'), 'hello');

// ============================================================
// 20. 自定义 parse 简写（追加不覆盖）
// ============================================================
container('#input.parse./^@(\d+)-(\d+)$/', fn($m) => ['range' => [(int)$m[1], (int)$m[2]]]);
test('自定义 parse @10-20 通过', filter(15, '@10-20'), 15);
test('自定义 parse @10-20 失败', filter(5, '@10-20'), null);
test('已存在简写 >=18 仍有效', filter(20, ['int', '>=18']), 20);
test('已存在简写 aa|bb|cc 仍有效', filter('aa', ['str', 'aa|bb|cc']), 'aa');
test('已存在简写 1..100 仍有效', filter(50, '1..100'), 50);
test('已存在简写 email 仍有效', filter('test@example.com', 'email'), 'test@example.com');

test();
