<?php
// serve.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\{middleware, test, container, from};
use function nx\middleware\prefab\serve;

// 初始化测试环境
$testDir = __DIR__ . '/_test_static';
@mkdir($testDir, 0755, true);
file_put_contents($testDir . '/test.txt', 'test content');
file_put_contents($testDir . '/test.js', 'var a = 1;');
file_put_contents($testDir . '/index.html', '<h1>Index</h1>');

// 清除所有缓存
container(null);

// 基本功能
test('serve: 静态文件存在时返回内容', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	return middleware(serve($testDir), 'not found');
}, 'test content');

test('serve: 文件不存在时继续下一个', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/nonexistent.txt']);
	return middleware(serve($testDir), 'fallback');
}, 'fallback');

test('serve: 目录自动追加index.html', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/']);
	return middleware(serve($testDir), 'not found');
}, '<h1>Index</h1>');

test('serve: 设置正确的Content-Type', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir), 'not found');
	return container('#out.response.headers.Content-Type');
}, fn($v) => str_starts_with($v, 'text/plain'));

// 缓存策略：null（默认）→ 不输出 Cache-Control
test('serve: 默认无Cache-Control', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir), 'not found');
	$h = container('#out.response.headers');
	return isset($h['Cache-Control']);
}, false);

// 缓存策略：false → 强制不缓存
test('serve: false输出no-cache', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, false), 'not found');
	return container('#out.response.headers.Cache-Control');
}, 'no-cache, no-store, must-revalidate');

// 缓存策略：int → max-age
test('serve: int作为max-age', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, 3600), 'not found');
	return container('#out.response.headers.Cache-Control');
}, 'public, max-age=3600');

// 缓存策略：ETag
test('serve: ETag条件缓存', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, 'etag'), 'not found');
	return container('#out.response.headers.ETag');
}, fn($v) => $v !== null && str_starts_with($v, '"'));

// 缓存策略：ETag 匹配时返回 304
test('serve: ETag匹配返回304', function() use ($testDir) {
	$file = $testDir . '/test.txt';
	$mtime = filemtime($file);
	$expectedTag = '"' . $mtime . '-' . filesize($file) . '"';
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#in.headers', ['if-none-match' => $expectedTag]);
	middleware(serve($testDir, 'etag'), 'not found');
	return container('#out.response.code');
}, 304);

// 缓存策略：Modified
test('serve: Last-Modified条件缓存', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, 'modified'), 'not found');
	return container('#out.response.headers.Last-Modified');
}, fn($v) => $v !== null);

// 缓存策略：Modified 匹配时返回 304
test('serve: Modified匹配返回304', function() use ($testDir) {
	$file = $testDir . '/test.txt';
	$expectedLm = gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT';
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#in.headers', ['if-modified-since' => $expectedLm]);
	middleware(serve($testDir, 'modified'), 'not found');
	return container('#out.response.code');
}, 304);

// 缓存策略：数组 control + age
test('serve: 数组配置 control + age', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#in.headers', []);
	container('#out.response', null);
	middleware(serve($testDir, ['control' => 'etag,modified', 'age' => 86400]), 'not found');
	$h = container('#out.response.headers');
	return $h['Cache-Control'] === 'public, max-age=86400' && str_starts_with($h['ETag'] ?? '', '"') && $h['Last-Modified'] !== null;
}, true);

// 缓存策略：容器配置
test('serve: 容器配置 #static:cache', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#static:cache', 7200);
	middleware(serve($testDir), 'not found');
	container('#static:cache', null);
	return container('#out.response.headers.Cache-Control');
}, 'public, max-age=7200');

// Content-Type for .js file
test('serve: js文件Content-Type正确', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.js']);
	middleware(serve($testDir), 'not found');
	return container('#out.response.headers.Content-Type');
}, 'application/javascript');

test();

// 阻止 shutdown 时输出残留响应体
container('#out.render', fn() => null);

// 清理测试文件
@unlink($testDir . '/test.txt');
@unlink($testDir . '/test.js');
@unlink($testDir . '/index.html');
@rmdir($testDir);
