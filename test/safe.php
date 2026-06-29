<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\safe;
use function ff\container;
use function ff\test;

test('成功返回结果', safe(fn() => 42), 42);
test('异常返回 null', safe(fn() => throw new \Exception('fail')), null);
test('带参数调用', safe(fn($a, $b) => $a + $b, 3, 4), 7);
test('除零异常', safe(fn($a, $b) => $a / $b, 10, 0), null);
test('类型错误异常', safe(fn(string $x) => $x, null), null);

// 注册异常处理器后验证
container('#safe', fn(\Throwable $e) => match(true){
    $e instanceof \InvalidArgumentException => $e->getMessage(),
    default => 'other',
});
test('处理器返回消息', safe(fn() => throw new \InvalidArgumentException('bad input')), 'bad input');
test('处理器匹配 default', safe(fn() => throw new \RuntimeException('runtime')), 'other');
test('处理器不影响正常结果', safe(fn() => 99), 99);
container('#safe', null); // 清理

test();
