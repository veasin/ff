<?php
require __DIR__ . '/../../vendor/autoload.php';

use function ff\{container, route, route\rest, test};

test('rest - list 处理器带规则', function(){
	container(null);
	$_GET = ['name' => 'test', 'email' => 'a@b.com'];
	container("#in.input", ['method' => 'get', 'uri' => '/user?name=test', 'params' => []]);
	$routes = rest([
		'list' => fn($input) => [$input],
	], rules: [
		'list' => ['name' => 'query,str', 'email' => 'query,email'],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => ['name' => 'test', 'email' => 'a@b.com'], 'code' => 200]);

test('rest - list 处理器无规则', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/user', 'params' => []]);
	$routes = rest([
		'list' => fn($input) => [['ok']],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => ['ok'], 'code' => 200]);

test('rest - create 处理器返回 201', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/user', 'params' => []]);
	container('#in.body', ['name' => 'John', 'email' => 'john@test.com']);
	$routes = rest([
		'create' => fn($input) => [$input, 201],
	], rules: [
		'create' => ['name' => 'str', 'email' => 'email'],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => ['name' => 'John', 'email' => 'john@test.com'], 'code' => 201]);

test('rest - get 处理器自动提取参数', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/user/42', 'params' => null]);
	$routes = rest([
		'get' => fn($input) => [$input],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => ['id' => '42'], 'code' => 200]);

test('rest - get 处理器含参数验证', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/user/42', 'params' => null]);
	$routes = rest([
		'get' => fn($input) => [$input],
	], '{id}', [
		'get' => ['id' => 'params,int'],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => ['id' => 42], 'code' => 200]);

test('rest - update 处理器合并参数和 body', function(){
	container(null);
	container("#in.input", ['method' => 'patch', 'uri' => '/user/7', 'params' => null]);
	container('#in.body', ['name' => 'Jane']);
	$routes = rest([
		'update' => fn($input) => [null, isset($input['id']) && $input['name'] === 'Jane' ? 204 : 400],
	], rules: [
		'update' => ['name' => 'str'],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => null, 'code' => 204]);

test('rest - replace 处理器 (PUT)', function(){
	container(null);
	container("#in.input", ['method' => 'put', 'uri' => '/user/7', 'params' => null]);
	container('#in.body', ['name' => 'Jane', 'email' => 'jane@test.com']);
	$routes = rest([
		'replace' => fn($input) => [$input],
	], rules: [
		'replace' => ['name' => 'str', 'email' => 'email'],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => ['name' => 'Jane', 'email' => 'jane@test.com', 'id' => '7'], 'code' => 200]);

test('rest - delete 处理器', function(){
	container(null);
	container("#in.input", ['method' => 'delete', 'uri' => '/user/3', 'params' => null]);
	$routes = rest([
		'delete' => fn($input) => [null, 204],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => null, 'code' => 204]);

test('rest - 自定义参数名', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/user/abc', 'params' => null]);
	$routes = rest([
		'get' => fn($input) => [$input],
	], '{slug}');
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => ['slug' => 'abc'], 'code' => 200]);

test('rest - 未知 key 被忽略', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/user', 'params' => []]);
	$routes = rest([
		'list'   => fn($input) => [$input],
		'unknown'=> fn($input) => ['should not appear'],
	]);
	route(['user/' => $routes]);
	return container('#out.response');
}, ['body' => [], 'code' => 200]);

register_shutdown_function(fn() => container('#out.response', ['body' => null, 'code' => 200, 'type' => 'http']));
