<?php
// gzip.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{middleware, test, container};
use function ff\middleware\prefab\gzip;

// 测试用例
test('gzip: 不压缩空结果', function() {
    container('#in.headers', null);
    return middleware(gzip(), null);
}, null);

test('gzip: 不压缩数组', function() {
    container('#in.headers', null);
    return middleware(gzip(), ['data' => 'test']);
}, ['data' => 'test']);

test('gzip: 客户端不支持时不压缩', function() {
    container('#in.headers', null);
    $_SERVER['HTTP_ACCEPT_ENCODING'] = 'identity';
    return middleware(gzip(), 'content');
}, 'content');

test('gzip: 压缩后更小时启用压缩', function() {
    container('#in.headers', null);
    $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';
    $longString = str_repeat('test content ', 1000);
    $result = middleware(gzip(), $longString);
    return strlen($result);
}, fn($v) => $v < 1000);
test();
