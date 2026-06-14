<?php
declare(strict_types=1);
namespace nx;
/**
 * 路由匹配，支持 CLI 和 Web 两种模式。
 * 路由格式: method:/path，支持 :param 和 {param} 参数，* 通配符
 * - 行尾 /* 匹配剩余所有路径段
 * - 中间 * 匹配单个路径段
 * 0 参触发：route() 延时执行收集的路由
 * bool 开关：route(true) 开启延时模式
 * null 清空：route(null) 清空已收集的路由
 *
 * 延时执行模式：
 * ```
 * route(true);                               // 开启延时模式
 * route('GET:/api/items', fn($next) => ...); // 收集但不执行
 * route('POST:/api/items', function($next) { ... });
 * route();                                    // 触发执行所有收集的路由
 * route(null);                                // 清空收集的路由
 * ```
 * 立即执行模式：
 * ```
 * route('GET:/api/items', fn($next) => output(loadItems()));
 * route(['GET:/list' => fn($next) => ..., 'POST:/create' => fn($next) => ...]);
 * ```
 * 多条路由匹配时，内部使用 middleware() 执行 handler，* 通配符支持阻断：
 * ```
 * route(['GET:/some/*' => fn($next) => 'wildcard',   // /some/action 匹配时，不调 $next 阻断后续
 *        'GET:/some/action' => fn($next) => 'action']);// 不会执行
 * route(['GET:/some/*' => fn($next) => $next(),       // 调 $next 放行
 *        'GET:/some/action' => fn($next) => 'action']);// 继续执行
 * ```
 * @param null|bool|string|array $match  匹配规则或路由映射数组
 *                                        true=开启延时模式
 *                                        null=清空已收集路由
 * @param callable              ...$fns  路由处理函数列表
 * @return mixed
 */
function route(null|bool|string|array $match = null, callable ...$fns): mixed{
	if(0 === func_num_args()){
		$deferred = container('#route.deferred');
		if(!is_array($deferred)) return null;
		container('#route.deferred', null);
		$routes = [];
		foreach($deferred as $item) $routes[$item[0]] = $item[1];
		return $routes ? route($routes) : null;
	}
	if($match === true) return container('#route.deferred', []);
	if($match === null) return container('#route.deferred', null);
	$deferred = container('#route.deferred');
	if(is_array($deferred)){
		if(is_array($match)){
			foreach($match as $m => $fn) $deferred[] = [$m, $fn];
		}else if($fns) $deferred[] = [$match, count($fns) === 1 ? $fns[0] : $fns];
		return container('#route.deferred', $deferred);
	}
	$handlers = [];
	$currentMethod = from('method', 'input');
	$params = from('params', 'input') ?? [];
	$reqSegments = $currentMethod === 'cli' ? [] : array_values(array_filter(explode('/', parse_url(from('uri', 'input'), PHP_URL_PATH) ?: '/')));
	foreach(is_array($match) ? $match : [$match => $fns] as $m => $fn){
		[$method, $uri] = explode(':', $m, 2) + ['', ''];
		$method = strtolower($method);
		if($method === 'cli'){
			$routeArgs = args(substr($m, 4));
			$matched = true;
			foreach($routeArgs as $k => $v){
				if(!isset($params[$k]) || ($v !== '*' && $v !== true && $params[$k] !== $v)){
					$matched = false;
					break;
				}
			}
			if($matched) $handlers = [...$handlers, ...is_array($fn) ? $fn : [$fn]];
			continue;
		}
		if($method !== '*' && $method !== '' && $method !== $currentMethod) continue;
		$routeSegments = array_values(array_filter(explode('/', trim($uri))));
		if($uri === '') $routeSegments = $reqSegments;
		$isWildcard = end($routeSegments) === '*';
		$reqIndex = 0;
		$param = [];
		foreach($routeSegments as $route){
			if($route === '*'){
				if($isWildcard) $reqIndex = count($reqSegments);
				continue;
			}
			$p = $route[0] ?? '';
			$req = $reqSegments[$reqIndex] ?? null;
			if($req === null) break;
			if($p === ':' || ($p === '{' && ($route[-1] ?? '') === '}')){
				$param[trim($route, ':{}')] = $req;
				$reqIndex++;
				continue;
			}
			if($route !== $req) continue;
			$reqIndex++;
		}
		if($reqIndex === count($reqSegments) && $reqIndex === count($routeSegments)){
			$params = [...$params, ...$param];
			$handlers = [...$handlers, ...is_array($fn) ? $fn : [$fn]];
		}
	}
	container('#in.params', $params);
	return $handlers ? middleware(...$handlers) : null;
}
