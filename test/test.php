<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\test;

// 简单比较 - 通过
test('数字比较', 5, 5);
test('字符串比较', 'hello', 'hello');
test('布尔值比较', true, true);
// 函数作为value - 通过
test('加法函数', function(){
	return 2 + 2;
}, 4);
// 函数作为assign（断言函数） - 通过
test('范围判断', 10, function($value){
	return $value > 5 && $value < 20;
});
// 数组比较 - 通过
test('数组比较', ['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2]);
// 复杂断言 - 通过
test('字符串包含', 'hello world', function($value){
	return str_contains($value, 'world') && strlen($value) > 5;
});
// 闭包作为value - 通过
test('乘法函数', fn() => 3 * 4, 12);
// 严格类型比较 - 通过
test('类型比较', '123', '123');
// 全色彩label - 预期失败
//test('[k]黑[r]红[g]绿[y]黄[b]蓝[m]品[c]青[w]白[n]灰[ :][:k]黑底[:r]红底[:g]绿底[:y]黄底[:b]蓝底[:m]品底[:c]青底[:w]白底[:][K]亮黑[R]亮红[G]亮绿[Y]亮黄[B]亮蓝[M]亮品[C]亮青[W]亮白[ :][:K]亮黑底[:R]亮红底[:G]亮绿底[:Y]亮黄底[:B]亮蓝底[:M]亮品底[:C]亮青底[:W]亮白底[:]全色彩失败用例', 1, 2);
// 异常类型断言 - 通过（auto instanceof）
test('除零异常类型', fn() => 1 / 0, \DivisionByZeroError::class);
// 闭包处理异常 - 通过
test('除零闭包处理', fn() => 1 / 0, fn($v) => $v instanceof \DivisionByZeroError);
// value抛异常 vs 具体值 - 预期失败
//test('除零值对比', fn() => 1 / 0, '不会走到');
test();