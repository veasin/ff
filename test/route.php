<?php
require __DIR__ . '/../vendor/autoload.php';

use function nx\{container, input, route, test};

test('路由 - 基础匹配', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123', 'params' => null]);
	return route('get:/users/{id}', fn($next) => true);
}, ['get:/users/{id}']);
test('路由 - 精确匹配', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users', 'params' => null]);
	return route('get:/users', fn($next) => true);
}, ['get:/users']);
test('路由 - GET方法匹配', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123', 'params' => null]);
	return route('get:/users/{id}', fn($next) => true);
}, ['get:/users/{id}']);
test('路由 - POST方法匹配', function(){
	container("#in.input", ['method' => 'post', 'uri' => '/users', 'params' => null]);
	return route('post:/users', fn($next) => true);
}, ['post:/users']);
test('路由 - 方法不匹配', function(){
	container("#in.input", ['method' => 'post', 'uri' => '/users', 'params' => null]);
	return route('get:/users', fn($next) => true);
}, null);
test('路由 - 参数提取', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123', 'params' => null]);
	return route('get:/users/{id}', fn($next) => input('id', 'params'));
}, ['get:/users/{id}']);
test('路由 - 多段参数', function(){
	container("#in.input", ['method' => 'get', 'uri' => '/users/123/edit', 'params' => null]);
	return route('get:/users/{id}/{action}', fn($next) => [
		'id' => input('id', 'params'),
		'action' => input('action', 'params'),
	]);
}, ['get:/users/{id}/{action}']);
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
	return route('cli: user', fn($next) => true);
}, ['cli: user']);
test('路由 - 路由映射数组', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/list', 'params' => []]);
	return route([
		'get:/api/list' => function($next){ return 'list'; },
		'post:/api/create' => function($next){ return 'create'; },
	]);
}, ['get:/api/list']);
test('路由 - 路由映射数组 POST', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/api/create', 'params' => []]);
	return route([
		'get:/api/list' => function($next){ return 'list'; },
		'post:/api/create' => function($next){ return 'create'; },
	]);
}, ['post:/api/create']);
test('路由 - 多路由调用', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/api/create', 'params' => []]);
	route('get:/api/list', function($next){ return 'list'; });
	return route('post:/api/create', function($next){ return 'create'; });
}, ['post:/api/create']);

test('路由 - 延时模式：收集单路由并触发执行', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/items', 'params' => []]);
	route(true);
	route('get:/api/items', fn($next) => 'deferred');
	return route();
}, ['get:/api/items']);
test('路由 - 延时模式：收集数组路由并触发执行', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/api/create', 'params' => []]);
	route(true);
	route([
		'get:/api/list' => fn($next) => 'list',
		'post:/api/create' => fn($next) => 'create',
	]);
	return route();
}, ['post:/api/create']);
test('路由 - 延时模式：混合单路由和数组路由', function(){
	container(null);
	container("#in.input", ['method' => 'put', 'uri' => '/api/items/5', 'params' => []]);
	route(true);
	route('get:/api/items', fn($next) => 'list');
	route('put:/api/items/{id}', fn($next) => 'update');
	route('delete:/api/items/{id}', fn($next) => 'delete');
	return route();
}, ['put:/api/items/{id}']);
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
}, ['get:/api/users']);
test('路由 - 空路径会错误匹配所有路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/', 'params' => []]);
	return route([
		'get:/test' => fn($next) => 'test',
		'get:/' => fn($next) => 'root',
	]);
}, ['get:/']);
test('路由 - 多次调用$next被阻止', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/test', 'params' => []]);
	$multiNext = function($next){
		$first = $next();
		$second = $next();
		return "first=$first,second=$second";
	};
	return [
		route('get:/test', $multiNext, fn($next) => 'world'),
		container('#route.result'),
	];
}, [['get:/test'], 'first=world,second=world']);
test('路由 - *通配符阻断后续路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/some/action', 'params' => []]);
	return route([
		'get:/some/*' => fn($next) => 'wildcard',
		'get:/some/action' => fn($next) => 'action',
	]);
}, ['get:/some/*', 'get:/some/action']);
test('路由 - *通配符放行后续路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/some/action', 'params' => []]);
	return route([
		'get:/some/*' => fn($next) => $next(),
		'get:/some/action' => fn($next) => 'action',
	]);
}, ['get:/some/*', 'get:/some/action']);
test('路由 - 方案一：组前缀 空键匹配前缀自身', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456', 'params' => []]);
	return route([':/root/{root}/game/{id}/'=>[
		'' => fn($next) => 'prefix-self',
	]]);
}, [':/root/{root}/game/{id}']);
test('路由 - 方案一：组前缀 *:* 通配符子路径', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456/anything', 'params' => []]);
	return route(['get:/root/{root}/game/{id}/'=>[
		'*:*' => fn($next) => 'wildcard-match',
	]]);
}, ['*:/root/{root}/game/{id}/*']);
test('路由 - 方案一：组前缀 post:run 方法+子路径', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/root/123/game/456/run', 'params' => []]);
	return route([':/root/{root}/game/{id}/'=>[
		'post:run' => fn($next) => 'run-handler',
	]]);
}, ['post:/root/{root}/game/{id}/run']);
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
}, ['get:/root/{root}/game/{id}/action']);
test('路由 - 方案一：组前缀 参数提取', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/abc/game/999/hello', 'params' => []]);
	return route(['get:/root/{root}/game/{id}/'=>[
		'hello' => fn($next) => input('root', 'params') . '-' . input('id', 'params'),
	]]);
}, ['get:/root/{root}/game/{id}/hello']);
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
}, [['A', 'B'], ['get:/prefix/run']]);
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
}, [['A', 'exe'], ['get:/prefix/exe']]);
test('路由 - 方案二：无公共后置处理器', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/prefix/sub', 'params' => []]);
	return route('get:/prefix/',
		fn($next) => 'outer',
		['sub' => fn($next) => 'sub-only'],
	);
}, ['get:/prefix/sub']);
test('路由 - 显式 method 匹配 DELETE', function(){
	container(null);
	container("#in.input", ['method' => 'delete', 'uri' => '/test/path', 'params' => []]);
	return route('delete:/test/path', fn($next) => 'match');
}, ['delete:/test/path']);
test('路由 - 裸路径路由（无方法前缀）匹配任意方法', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/bare-path', 'params' => []]);
	return route('/bare-path', fn($next) => 'bare-path-match');
}, ['/bare-path']);
test('路由 - 子路由中 * 为通配符', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456/extra/deep', 'params' => []]);
	return route(['get:/root/{root}/game/{id}/'=>[
		'*' => fn($next) => 'sub-wildcard',
	]]);
}, ['get:/root/{root}/game/{id}/*']);
test('路由 - 子路由中 : 等效于空键(前缀匹配)', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/123/game/456', 'params' => []]);
	return route([':/root/{root}/game/{id}/'=>[
		':' => fn($next) => 'colon-prefix',
	]]);
}, [':/root/{root}/game/{id}']);
test('路由 - 子映射多子键均注册：通配符路径', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/abc/game/999/other', 'params' => []]);
	return route(['*:/root/{root}/game/{id}/'=>[
		'*' => fn($next) => 'wildcard-result',
		'get:exe' => fn($next) => 'exe-result',
	]]);
}, ['*:/root/{root}/game/{id}/*']);
test('路由 - 子映射多子键均注册：具体路径', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/root/abc/game/999/exe', 'params' => []]);
	return route(['*:/root/{root}/game/{id}/'=>[
		'*' => fn($next) => $next(),
		'get:exe' => fn($next) => 'exe-result',
	]]);
}, ['*:/root/{root}/game/{id}/*', 'get:/root/{root}/game/{id}/exe']);
test('路由 - 尾部/无差异：匹配路径尾部带/', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/users/', 'params' => []]);
	return route('get:/users', fn($next) => 'no-slash-route');
}, ['get:/users']);
test('路由 - 尾部/无差异：路由模式尾部带/', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/users', 'params' => []]);
	return route('get:/users/', fn($next) => 'slash-route');
}, ['get:/users/']);
test('路由 - 返回值存容器', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/items', 'params' => []]);
	route('get:/api/items', fn($next) => 'result-value');
	return container('#route.result');
}, 'result-value');
test('路由 - 不匹配时容器返回 null', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/none', 'params' => []]);
	route('get:/api/items', fn($next) => 'value');
	return container('#route.result');
}, null);
test('路由 - 嵌套子路由：三层嵌套', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/a/b/c/d', 'params' => []]);
	return route(['get:/a/'=>['b/'=>['c/'=>['d'=>fn($next)=>'deep']]]]);
}, ['get:/a/b/c/d']);
test('路由 - 嵌套子路由：四层嵌套', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/w/x/y/z', 'params' => []]);
	return route(['*:/w/'=>['x/'=>['y/'=>['z'=>fn($next)=>'deep']]]]);
}, ['*:/w/x/y/z']);
test('路由 - 嵌套子路由：嵌套+外层 before/after', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/level1/level2/deep', 'params' => []]);
	$track = [];
	$r = route('get:/level1/',
		function($next) use (&$track){ $track[] = 'A'; return $next(); },
		['level2/' => ['deep' => function($next) use (&$track){ $track[] = 'B'; return 'B-val'; }]],
		function($next) use (&$track){ $track[] = 'C'; return $next(); },
	);
	return [$track, $r];
}, [['A', 'B'], ['get:/level1/level2/deep']]);
test('路由 - 嵌套子路由：内外全包裹', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/level1/level2/deep', 'params' => []]);
	$track = [];
	$r = route('get:/level1/',
		function($next) use (&$track){ $track[] = 'A'; return $next(); },
		['level2/' => [
			function($next) use (&$track){ $track[] = 'B'; return $next(); },
			['deep' => function($next) use (&$track){ $track[] = 'C'; return $next(); }],
			function($next) use (&$track){ $track[] = 'D'; return $next(); },
		]],
		function($next) use (&$track){ $track[] = 'E'; return $track; },
	);
	return [$track, $r];
}, [['A', 'B', 'C', 'D', 'E'], ['get:/level1/level2/deep']]);
test('路由 - 嵌套子路由：方法继承穿透', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/a/b/c', 'params' => []]);
	return route(['post:/a/'=>['b/'=>['c'=>fn($next)=>'match']]]);
}, ['post:/a/b/c']);
test('路由 - 嵌套子路由：方法覆盖', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/a/b/c', 'params' => []]);
	return route(['*:/a/'=>['post:b'=>['c'=>fn($next)=>'override']]]);
}, ['post:/a/b/c']);
test('路由 - 嵌套子路由：同级混合直接+嵌套', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/a/b/nested/deep', 'params' => []]);
	return route(['get:/a/'=>[
		'flat' => fn($next) => 'flat',
		'b/' => ['nested/' => ['deep' => fn($next) => 'nested-val']],
	]]);
}, ['get:/a/b/nested/deep']);
test('路由 - 嵌套子路由：通配符多级', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/a/b/c/anything/here', 'params' => []]);
	return route(['get:/a/'=>['b/'=>['c/'=>['*'=>fn($next)=>'wild-nest']]]]);
}, ['get:/a/b/c/*']);
test('路由 - 嵌套子路由：不匹配返回null', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/a/b/other', 'params' => []]);
	return route(['get:/a/'=>['b/'=>['deep'=>fn($next)=>'val']]]);
}, null);
test('路由 - 嵌套子路由：前缀匹配空键', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/a/b', 'params' => []]);
	return route(['get:/a/'=>['b/'=>[''=>fn($next)=>'prefix']]]);
}, ['get:/a/b']);
test('路由 - 分组中间件：无子路由时保留原始列表', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/mixed', 'params' => []]);
	return route(['get:/mixed' => [
		fn($next) => 'A',
		[fn($next) => 'B1', fn($next) => 'B2'],
		fn($next) => 'C',
	]]);
}, ['get:/mixed']);
test('路由 - 分组中间件 + 子路由：分组内函数进入累积栈', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/group/run', 'params' => []]);
	$track = [];
	$r = route(['get:/group/' => [
		function($next) use (&$track){ $track[] = 'A'; return $next(); },
		[
			function($next) use (&$track){ $track[] = 'B'; return $next(); },
			function($next) use (&$track){ $track[] = 'C'; return $next(); },
		],
		function($next) use (&$track){ $track[] = 'D'; return $next(); },
		'run' => function($next) use (&$track){ $track[] = 'E'; return 'val'; },
		function($next) use (&$track){ $track[] = 'F'; return $next(); },
	]]);
	return [$track, $r];
}, [['A', 'B', 'C', 'D', 'E'], ['get:/group/run']]);
test('路由 - 分组中间件 + 子路由：交错模式', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/mix/k1', 'params' => []]);
	$track1 = [];
	$r1 = route(['get:/mix/' => [
		function($next) use (&$track1){ $track1[] = 'b1'; return $next(); },
		[
			function($next) use (&$track1){ $track1[] = 'b2'; return $next(); },
		],
		'k1' => function($next) use (&$track1){ $track1[] = 'h1'; return 'val1'; },
		function($next) use (&$track1){ $track1[] = 'a1'; return $next(); },
		[
			function($next) use (&$track1){ $track1[] = 'a2'; return $next(); },
		],
	]]);
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/mix/k2', 'params' => []]);
	$track2 = [];
	$r2 = route(['get:/mix/' => [
		function($next) use (&$track2){ $track2[] = 'b1'; return $next(); },
		[
			function($next) use (&$track2){ $track2[] = 'b2'; return $next(); },
		],
		'k1' => function($next) use (&$track2){ $track2[] = 'h1'; return 'val1'; },
		function($next) use (&$track2){ $track2[] = 'a1'; return $next(); },
		'k2' => function($next) use (&$track2){ $track2[] = 'h2'; return 'val2'; },
		[
			function($next) use (&$track2){ $track2[] = 'a2'; return $next(); },
		],
	]]);
	return [$track1, $r1, $track2, $r2];
}, [['b1', 'b2', 'h1'], ['get:/mix/k1'], ['b1', 'b2', 'a1', 'h2'], ['get:/mix/k2']]);
test('路由 - 分组中间件：嵌套子路由内分组', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/nest/deep', 'params' => []]);
	$track = [];
	$r = route(['get:/nest/' => [
		fn($next) => $next(),
		'deep' => [
			[fn($next) => 'inner-group'],
			fn($next) => 'after',
		],
	]]);
	return [$track, $r];
}, [[], ['get:/nest/deep']]);
test('路由 - 缺省key通配符：包裹其他路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/any/path', 'params' => []]);
	$keys = route([
		fn($next) => 'wrap(' . $next() . ')',
		'get:/any/path' => fn($next) => 'inner',
	]);
	return ['keys' => $keys, 'result' => container('#route.result')];
}, ['keys' => ['*', 'get:/any/path'], 'result' => 'wrap(inner)']);
test('路由 - 缺省key通配符：阻断后续路由', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/block/test', 'params' => []]);
	$keys = route([
		fn($next) => 'blocked',
		'get:/block/test' => fn($next) => 'should-not-reach',
	]);
	return ['keys' => $keys, 'result' => container('#route.result')];
}, ['keys' => ['*', 'get:/block/test'], 'result' => 'blocked']);
test('路由 - 缺省key通配符：匹配任意深度路径', function(){
	container(null);
	container("#in.input", ['method' => 'post', 'uri' => '/a/b/c', 'params' => []]);
	$keys = route([
		fn($next) => 'catch:' . $next(),
		'post:/a/b/c' => fn($next) => 'deep',
	]);
	return ['keys' => $keys, 'result' => container('#route.result')];
}, ['keys' => ['*', 'post:/a/b/c'], 'result' => 'catch:deep']);
test('路由 - 缺省key通配符：不干扰子路由组', function(){
	container(null);
	container("#in.input", ['method' => 'get', 'uri' => '/api/users', 'params' => []]);
	$keys = route([
		fn($next) => 'wrap(' . $next() . ')',
		'api/' => [
			fn($next) => $next(['role' => 'admin']),
			'get:users' => fn($next, $ctx) => 'users:' . ($ctx['role'] ?? 'none'),
		],
	]);
	return ['keys' => $keys, 'result' => container('#route.result')];
}, ['keys' => ['*', 'get:api/users'], 'result' => 'wrap(users:admin)']);
test();
