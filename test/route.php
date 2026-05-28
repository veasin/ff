<?php
require __DIR__ . '/_boot.php';

use function nx\{container, input, route, test};

test('路由 - 基础匹配', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123', 'params' => null]);
	return route('get:/users/:id', function($next){
		return true;
	});
}, true);
test('路由 - 精确匹配', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users', 'params' => null]);
	return route('get:/users', function($next){
		return true;
	});
}, true);
test('路由 - GET方法匹配', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123', 'params' => null]);
	return route('get:/users/:id', function($next){
		return true;
	});
}, true);
test('路由 - POST方法匹配', function(){
	container("#in.input", ['method' => 'post', 'uri' => '/users', 'params' => null]);
	return route('post:/users', function($next){
		return true;
	});
}, true);
test('路由 - 方法不匹配', function(){
	container("#in.input", ['method' => 'post', 'uri' => '/users', 'params' => null]);
	return route('get:/users', function($next){
		return true;
	});
}, null);
test('路由 - 参数提取', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123', 'params' => null]);
	return route('get:/users/:id', function($next){
		return input('id', 'params');
	});
}, '123');
test('路由 - 多段参数', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123/edit', 'params' => null]);
	return route('get:/users/:id/:action', function($next){
		return [
			'id' => input('id', 'params'),
			'action' => input('action', 'params'),
		];
	});
}, ['id' => '123', 'action' => 'edit']);
test('路由 - 不匹配返回null', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/posts/123', 'params' => null]);
	return route('get:/users/:id', function($next){});
}, null);
test('路由 - params容器写入', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/456', 'params' => null]);
	route('get:/users/:id', function($next){});
	return input('id', 'params');
}, '456');
test('路由 - CLI模式匹配', function(){
	container("#in.input", [
		'method' => 'cli',
		'uri' => 'app.php user list --limit=10',
		'params' => ['user', 'list', 'limit' => '10'],
	]);
	return route('cli: user', function($next){
		return true;
	});
}, true);
test('路由 - 路由映射数组', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/list', 'params' => []]);
	return route([
		'get:/api/list' => function($next){ return 'list'; },
		'post:/api/create' => function($next){ return 'create'; },
	]);
}, 'list');
test('路由 - 路由映射数组 POST', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/api/create', 'params' => []]);
	return route([
		'get:/api/list' => function($next){ return 'list'; },
		'post:/api/create' => function($next){ return 'create'; },
	]);
}, 'create');
test('路由 - 多路由调用', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/api/create', 'params' => []]);
	$result1 = route('get:/api/list', function($next){ return 'list'; });
	$result2 = route('post:/api/create', function($next){ return 'create'; });
	return $result2;  // 只返回最后匹配的
}, 'create');

test('路由 - 空路径会错误匹配所有路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/', 'params' => []]);
	return route([
		'get:/test' => fn($next) => 'test',
		'get:/' => fn($next) => 'root',
	]);
}, 'root');  // 期望只匹配 'get:/'
test();