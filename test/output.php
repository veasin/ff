<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, output, test};

// 共享 cap emit：将捕获结果存入容器 key，避免文件级变量引用混乱
container('^#ext.out.emit.cap', function(mixed $content, array $meta){
	container('#out.captured', ['data' => $content, 'meta' => $meta]);
});

test('JSON 基本输出', function(){
	container(null);
	container('#out.emit', 'cap');
	output(['a' => 1, 'b' => 2]);
	output();
	return container('#out.captured');
}, function($v){
	return $v['data'] === json_encode(['a' => 1, 'b' => 2])
		&& $v['meta']['code'] === 200
		&& ($v['meta']['headers']['Content-Type'] ?? '') === 'application/json; charset=UTF-8';
});

test('自定义状态码', function(){
	container(null);
	container('#out.emit', 'cap');
	output('not found', 404);
	output();
	return container('#out.captured');
}, function($v){
	return $v['meta']['code'] === 404;
});

test('Status enum', function(){
	container(null);
	container('#out.emit', 'cap');
	output(\ff\output\status::NotFound);
	output();
	return container('#out.captured');
}, function($v){
	return $v['meta']['code'] === 404 && $v['data'] === null;
});

test('视图模板简写', function(){
	container(null);
	container('#out.emit', 'cap');
	output(['title' => 'Hello'], 'nonexistent.php');
	output();
	return container('#out.captured');
}, function($v){
	return ($v['meta']['type'] ?? null) === 'view'
		&& ($v['meta']['file'] ?? null) === 'nonexistent.php';
});

test('文件输出', function(){
	container(null);
	container('#out.emit', 'cap');
	output('download', ['type' => 'file', 'file' => '/tmp/test.txt']);
	output();
	return container('#out.captured');
}, function($v){
	return ($v['meta']['type'] ?? null) === 'file'
		&& ($v['meta']['file'] ?? null) === '/tmp/test.txt';
});

test('204 空 body', function(){
	container(null);
	container('#out.emit', 'cap');
	output(null, 204);
	output();
	return container('#out.captured');
}, function($v){
	return $v['data'] === null && $v['meta']['code'] === 204;
});

test('500 错误', function(){
	container(null);
	container('#out.emit', 'cap');
	output('err', 500);
	output();
	return container('#out.captured');
}, function($v){
	return $v['data'] === json_encode('err') && $v['meta']['code'] === 500;
});

test('自定义 headers', function(){
	container(null);
	container('#out.emit', 'cap');
	output('ok', ['headers' => ['X-Custom' => 'val']]);
	output();
	return container('#out.captured');
}, function($v){
	return ($v['meta']['headers']['X-Custom'] ?? '') === 'val';
});

test('自定义 format 扩展', function(){
	container(null);
	container('^#ext.out.type.uc', fn($data, $meta): ?array => [strtoupper((string)$data), $meta]);
	container('#out.emit', 'cap');
	output('hello', ['type' => 'uc']);
	output();
	container('^#ext.out.type.uc', null);
	return container('#out.captured');
}, function($v){
	return $v['data'] === 'HELLO';
});

test('自定义 emit 扩展', function(){
	$called = false;
	container('^#ext.out.emit.test', function($content, $meta) use (&$called){ $called = true; });
	container('#out.emit', 'test');
	output('emit test');
	output();
	container('^#ext.out.emit.test', null);
	return $called;
}, true);

test('无数据时 0 参静默跳过', function(){
	container(null);
	container('#out.emit', 'cap');
	output();
	return container('#out.captured');
}, null);

test();
container('^#ext.out.emit.cap', null);
