<?php
// error.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\{middleware, test, container};
use function nx\middleware\prefab\error;

// 测试用例
test('error: 正常执行返回结果', function() {
    return middleware(error(), fn() => 'ok');
}, 'ok');

test('error: 异常返回通用错误', function() {
    container('#out.response', null);
    middleware(error(), function() {
        throw new \RuntimeException('Test error', 500);
    });
    return container('#out.response.body.error');
}, 'Internal server error');

test('error: debug模式显示详细错误', function() {
    container('#out.response', null);
    middleware(error(debug: true), function() {
        throw new \RuntimeException('Detailed error', 500);
    });
    return container('#out.response.body.error');
}, 'Detailed error');
test();
