<?php
include __DIR__ . "/_boot.php";

use function nx\safe;
use function nx\test;

test('成功返回结果', safe(fn() => 42), 42);
test('异常返回 null', safe(fn() => throw new \Exception('fail')), null);
test('带参数调用', safe(fn($a, $b) => $a + $b, 3, 4), 7);
test('除零异常', safe(fn($a, $b) => $a / $b, 10, 0), null);
test('类型错误异常', safe(fn(string $x) => $x, null), null);

test();
