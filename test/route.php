<?php
require __DIR__ . '/../vendor/autoload.php';

use function nx\{container, input, route, test};

test('路由 - 基础匹配', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123', 'params' => null]);
	return route('get:/users/{id}', function($next){
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
	return route('get:/users/{id}', function($next){
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
	return route('get:/users/{id}', function($next){
		return input('id', 'params');
	});
}, '123');
test('路由 - 多段参数', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123/edit', 'params' => null]);
	return route('get:/users/{id}/{action}', function($next){
		return [
			'id' => input('id', 'params'),
			'action' => input('action', 'params'),
		];
	});
}, ['id' => '123', 'action' => 'edit']);
test('路由 - 不匹配返回null', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/posts/123', 'params' => null]);
	return route('get:/users/{id}', function($next){});
}, null);
test('路由 - params容器写入', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/456', 'params' => null]);
	route('get:/users/{id}', function($next){});
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

test('路由 - 延时模式：收集单路由并触发执行', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/items', 'params' => []]);
	route(true);
	route('get:/api/items', fn($next) => 'deferred');
	return route();
}, 'deferred');
test('路由 - 延时模式：收集数组路由并触发执行', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/api/create', 'params' => []]);
	route(true);
	route([
		'get:/api/list' => fn($next) => 'list',
		'post:/api/create' => fn($next) => 'create',
	]);
	return route();
}, 'create');
test('路由 - 延时模式：混合单路由和数组路由', function(){
	container(null);
	container("#in.input", ['method' => 'put', 'uri' => '/api/items/5', 'params' => []]);
	route(true);
	route('get:/api/items', fn($next) => 'list');
	route('put:/api/items/{id}', fn($next) => 'update');
	route('delete:/api/items/{id}', fn($next) => 'delete');
	return route();
}, 'update');
test('路由 - 延时模式：不匹配返回null', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/posts', 'params' => []]);
	route(true);
	route('get:/api/items', fn($next) => 'items');
	route('post:/api/items', fn($next) => 'create');
	return route();
}, null);
test('路由 - 延时模式：未开启时route()无效应返回null', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/items', 'params' => []]);
	return route();
}, null);
test('路由 - 延时模式：可多次开启', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/users', 'params' => []]);
	route(true);
	route('get:/api/items', fn($next) => 'items');
	route(true);  // 第二次开启，清空之前收集的
	route('get:/api/users', fn($next) => 'users');
	return route();
}, 'users');
test('路由 - 空路径会错误匹配所有路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/', 'params' => []]);
	return route([
		'get:/test' => fn($next) => 'test',
		'get:/' => fn($next) => 'root',
	]);
}, 'root');  // 期望只匹配 'get:/'
test('路由 - 多次调用$next被阻止', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/test', 'params' => []]);
	$multiNext = function($next){
		$first = $next();
		$second = $next();
		return "first=$first,second=$second";
	};
	return route('get:/test', $multiNext, fn($next) => 'world');
}, 'first=world,second=world');
test('路由 - *通配符阻断后续路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/some/action', 'params' => []]);
	return route([
		'get:/some/*' => fn($next) => 'wildcard',
		'get:/some/action' => fn($next) => 'action',
	]);
}, 'wildcard');
test('路由 - *通配符放行后续路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/some/action', 'params' => []]);
	return route([
		'get:/some/*' => fn($next) => $next(),
		'get:/some/action' => fn($next) => 'action',
	]);
}, 'action');
test('路由 - 方案一：组前缀 空键匹配前缀自身', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456', 'params' => []]);
	return route([':/root/{root}/game/{id}/'=>[
		'' => fn($next) => 'prefix-self',
	]]);
}, 'prefix-self');
test('路由 - 方案一：组前缀 *:* 通配符子路径', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456/anything', 'params' => []]);
	return route(['get:/root/{root}/game/{id}/'=>[
		'*:*' => fn($next) => 'wildcard-match',
	]]);
}, 'wildcard-match');
test('路由 - 方案一：组前缀 post:run 方法+子路径', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/root/123/game/456/run', 'params' => []]);
	return route([':/root/{root}/game/{id}/'=>[
		'post:run' => fn($next) => 'run-handler',
	]]);
}, 'run-handler');
test('路由 - 方案一：组前缀 方法不匹配', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456/run', 'params' => []]);
	return route([':/root/{root}/game/{id}/'=>[
		'post:run' => fn($next) => 'only-post',
	]]);
}, null);
test('路由 - 方案一：组前缀 纯路径+继承方法', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456/action', 'params' => []]);
	return route(['get:/root/{root}/game/{id}/'=>[
		'action' => fn($next) => 'action-handler',
	]]);
}, 'action-handler');
test('路由 - 方案一：组前缀 参数提取', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/abc/game/999/hello', 'params' => []]);
	return route(['get:/root/{root}/game/{id}/'=>[
		'hello' => fn($next) => input('root', 'params') . '-' . input('id', 'params'),
	]]);
}, 'abc-999');
test('路由 - 方案二：智能子路由展开', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/prefix/run', 'params' => []]);
	$track = [];
	$r = route('get:/prefix/',
		function($next) use (&$track){ $track[] = 'A'; return $next(); },
		['run' => function($next) use (&$track){ $track[] = 'B'; return 'B-val'; }],
		function($next) use (&$track){ $track[] = 'C'; return 'C-val'; },
	);
	return [$track, $r];
}, [['A', 'B'], 'B-val']);
test('路由 - 方案二：多子路径分别匹配', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/prefix/exe', 'params' => []]);
	$track = [];
	$r = route('get:/prefix/',
		function($next) use (&$track){ $track[] = 'A'; return $next(); },
		['run' => function($next) use (&$track){ $track[] = 'run'; return 'run-val'; },
		 'exe' => function($next) use (&$track){ $track[] = 'exe'; return 'exe-val'; }],
		function($next) use (&$track){ $track[] = 'C'; return 'C-val'; },
	);
	return [$track, $r];
}, [['A', 'exe'], 'exe-val']);
test('路由 - 方案二：无公共后置处理器', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/prefix/sub', 'params' => []]);
	return route('get:/prefix/',
		fn($next) => 'outer',
		['sub' => fn($next) => 'sub-only'],
	);
}, 'outer');
test('路由 - 显式 method 匹配 DELETE', function(){
	container(null);
	container("#in.input", ['method' => 'delete', 'uri' => '/test/path', 'params' => []]);
	return route('delete:/test/path', fn($next) => 'match');
}, 'match');
test('路由 - 裸路径路由（无方法前缀）匹配任意方法', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/bare-path', 'params' => []]);
	return route('/bare-path', fn($next) => 'bare-path-match');
}, 'bare-path-match');
test('路由 - 子路由中 * 为通配符', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456/extra/deep', 'params' => []]);
	return route(['get:/root/{root}/game/{id}/'=>[
		'*' => fn($next) => 'sub-wildcard',
	]]);
}, 'sub-wildcard');
test('路由 - 子路由中 : 等效于空键(前缀匹配)', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456', 'params' => []]);
	return route([':/root/{root}/game/{id}/'=>[
		':' => fn($next) => 'colon-prefix',
	]]);
}, 'colon-prefix');
test('路由 - 尾部/无差异：匹配路径尾部带/', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/users/', 'params' => []]);
	return route('get:/users', fn($next) => 'no-slash-route');
}, 'no-slash-route');
test('路由 - 尾部/无差异：路由模式尾部带/', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/users', 'params' => []]);
	return route('get:/users/', fn($next) => 'slash-route');
}, 'slash-route');
test();