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
// 故意失败一个测试 - 显示红色
test('失败示例', 100, 200);
// 另一个失败示例 - 显示红色
test('类型比较', '123', 123);
test();