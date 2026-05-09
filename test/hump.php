<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{hump, test};

// 定义测试中间件
$wrapper = fn($next, ...$args) => '(' . $next(...$args) . ')';
$fn1 = fn($next, ...$args) => '1';
$fn2 = fn($next, ...$args) => '2';
$fn3 = fn($next, ...$args) => '3';
$fn4 = fn($next, ...$args) => '4';
$noReturn = function($next, ...$args){};
// 创建临时文件中间件
$file1 = __DIR__ . '/test_mw1.php';
$file2 = __DIR__ . '/test_mw2.php';
file_put_contents($file1, '<?php return "f1(" . $next() . ")f1";');
file_put_contents($file2, '<?php return "f2";');
// ==================== 测试用例 ====================
test('空列表', hump(), null);
test('单个函数有返回', hump($fn1), '1');
test('单个函数无返回', hump($noReturn), null);
test('纯链式 fn1->fn2->fn3', hump($fn1, $fn2, $fn3), '3');
test('纯链式 fn1->fn2->fn3 带返回值混合', hump($fn1, $fn2, $noReturn), null);
test('纯链式 fn1->fn2->fn3 无返回', hump($noReturn, $noReturn, $fn3), '3');
test('三层嵌套 fn1{fn2{fn3}}', hump($wrapper, $wrapper, $fn3), '((3))');
test('两层嵌套 fn1{fn2}->fn3', hump($wrapper, $fn2, $fn3), '3');
test('fn1->fn2{fn3}', hump($fn1, $wrapper, $fn3), '(3)');
test('fn1{fn2{fn3}}->fn4', hump($wrapper, $wrapper, $fn3, $fn4), '4');
test('混合嵌套和链式', hump($wrapper, $fn1, $wrapper, $fn2, $fn3), '3');
test('带初始值',
	hump(5,
		fn($next, $v) => $next($v * 2), fn($next, $v) => $v + 1
	),
	11
);
test('多个非函数参数',
	hump(1,
		2,
		3,
		fn($next, $v) => $v * 2
	),
	6
);
test('多参数传递',
	hump(fn($next) => $next(1, 2, 3),
		fn($next, $a, $b, $c) => $next($a + $b + $c), fn($next, $sum) => $sum * 2),
	12
);
test('文件中间件嵌套', hump($file1, $wrapper, $fn3), 'f1((3))f1');
test('文件中间件链式', hump($file2, $fn1, $fn2), '2');  // file2 返回 'f2' 但被忽略，最终 fn2 返回 '2'
test('文件中间件无返回', hump($file2, $noReturn), null);
// 清理临时文件
unlink($file1);
unlink($file2);
