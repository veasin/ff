<?php
// serve.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\{middleware, test, container, from};
use function nx\middleware\prefab\serve;

// 初始化测试环境
$testDir = __DIR__ . '/_test_static';
@mkdir($testDir, 0755, true);
file_put_contents($testDir . '/test.txt', 'test content');
file_put_contents($testDir . '/index.html', '<h1>Index</h1>');

// 清除所有缓存
container(null);
container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);

// 测试用例
test('serve: 静态文件存在时返回内容', function() use ($testDir) {
    return middleware(serve($testDir), fn($next) => 'not found');
}, 'test content');

test('serve: 文件不存在时继续下一个', function() use ($testDir) {
    container('#in.input', ['method' => 'GET', 'uri' => '/nonexistent.txt']);
    return middleware(serve($testDir), fn($next) => 'fallback');
}, 'fallback');

test('serve: 目录自动追加index.html', function() use ($testDir) {
    container('#in.input', ['method' => 'GET', 'uri' => '/']);
    return middleware(serve($testDir), fn($next) => 'not found');
}, '<h1>Index</h1>');

test('serve: 设置正确的Content-Type', function() use ($testDir) {
    container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
    middleware(serve($testDir), fn($next) => 'not found');
    return container('#out.response.headers.Content-Type');
}, fn($v) => str_starts_with($v, 'text/plain'));

// 清理测试文件（在 shutdown 后执行）
register_shutdown_function(function() use ($testDir) {
    @unlink($testDir . '/test.txt');
    @unlink($testDir . '/index.html');
    @rmdir($testDir);
});
