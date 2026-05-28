<?php
// json.php 测试
include __DIR__ . "/../../_boot.php";

use function nx\{middleware, test, container};
use function nx\middleware\prefab\json;

// 测试用例
test('json: null返回null', function() {
    return middleware(json(), fn() => null);
}, null);

test('json: 数组直接返回', function() {
    return middleware(json(), fn() => ['key' => 'value']);
}, ['key' => 'value']);

test('json: JSON字符串转换为数组', function() {
    return middleware(json(), fn() => '{"key":"value"}');
}, ['key' => 'value']);

test('json: 设置Content-Type', function() {
    middleware(json(), fn() => ['test' => 123]);
    return container('#out.response.headers.Content-Type');
}, fn($v) => str_contains($v ?? '', 'application/json'));
test();
