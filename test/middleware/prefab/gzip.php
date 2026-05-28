<?php
// gzip.php 测试
include __DIR__ . "/../../_boot.php";

use function nx\{middleware, test, container};
use function nx\middleware\prefab\gzip;

// 测试用例
test('gzip: 不压缩空结果', function() {
    container('#in.headers', null);
    return middleware(gzip(), fn($next) => null);
}, null);

test('gzip: 不压缩数组', function() {
    container('#in.headers', null);
    return middleware(gzip(), fn($next) => ['data' => 'test']);
}, ['data' => 'test']);

test('gzip: 客户端不支持时不压缩', function() {
    container('#in.headers', null);
    $_SERVER['HTTP_ACCEPT_ENCODING'] = 'identity';
    return middleware(gzip(), fn($next) => 'content');
}, 'content');

test('gzip: 压缩后更小时启用压缩', function() {
    container('#in.headers', null);
    $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
    $longString = str_repeat('test content ', 1000);
    $result = middleware(gzip(), fn($next) => $longString);
    return strlen($result);
}, fn($v) => $v < 1000);
test();
