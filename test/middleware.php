<?php
// 测试文件：test_middleware.php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{middleware, test};

// ==================== 定义测试中间件 ====================
$wrap = fn($next, ...$args) => '(' . $next(...$args) . ')';
$add1 = fn($next, $v) => $next($v + 1);
$double = fn($next, $v) => $next($v * 2);
$return3 = fn($next) => 3;                     // 不调用 $next
$noReturn = function($next){};                 // 不调用 $next，无返回值 => null
$identity = fn($next, $v) => $next($v);         // 透传
$multi = function($next){                       // 多次调用 $next
	$first = $next();
	$second = $next();                           // 第二次应被阻止
	return "first=$first,second=$second";
};
// ==================== 创建临时文件中间件 ====================
$file1 = __DIR__ . '/test_mw1.php';
$file2 = __DIR__ . '/test_mw2.php';
file_put_contents($file1, '<?php return "f1(" . $next() . ")f1";');
file_put_contents($file2, '<?php return "f2";');
// ==================== 测试用例 ====================
test('空列表', middleware(), null);
test('单个中间件有返回', middleware($return3), 3);
test('单个中间件无返回', middleware($noReturn), null);
test('两个中间件均调用 $next', middleware(5, $add1, $double), 12);
test('中间件阻断（中间不调用 $next）', middleware(5, $add1, $return3, $double), 3);
test('三层嵌套', middleware($wrap, $wrap, fn($next) => 'hello'), '((hello))');
test('初始值传递（非函数参数）', middleware(10, $double, $identity), 20);
test('文件中间件嵌套', middleware($file1, $wrap, fn($next) => 'end'), 'f1((end))f1');
test('文件中间件阻断', middleware($file2, $return3), 'f2');  // file2 返回 'f2'，阻断后续
test('多次调用 $next 被阻止', middleware($multi, fn($next) => 'world'), 'first=world,second=world');
test();
// 清理临时文件
unlink($file1);
unlink($file2);
