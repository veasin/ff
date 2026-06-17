<?php
declare(strict_types=1);
namespace nx;
/**
 * 路由匹配，支持 CLI 和 Web 两种模式。
 * 核心规则：未显式指定的部分从父级继承；顶级路由的隐式父级是 ['*', '/']
 * 路由键统一模型：`method:path`
 * - 无冒号 → 整个字符串为 path，method 继承父级
 * - 有冒号 → 左 method、右 path；空侧从父继承
 * - '' 或 ':' → 两侧继承（前缀匹配）
 * - 行尾 /* 匹配剩余所有路径段
 * - 中间 * 匹配单个路径段
 * 0 参触发：route() 延时执行收集的路由
 * bool 开关：route(true) 开启延时模式
 * null 清空：route(null) 清空已收集的路由
 * 延时执行模式：
 * ```
 * route(true);
 * route('GET:/api/items', fn($next) => ...);
 * route('POST:/api/items', function($next) { ... });
 * route();
 * route(null);
 * ```
 * 立即执行模式：
 * ```
 * route('GET:/api/items', fn($next) => output(loadItems()));
 * route(['GET:/list' => fn($next) => ..., 'POST:/create' => fn($next) => ...]);
 * ```
 * 组前缀展开：子映射中的子键自动拼接父路径
 * ```
 * route(['get:/root/{root}/game/{id}/'=>[
 *     'post:run' => fn($next) => ...,    // → get:/root/{root}/game/{id}/run
 *     'action'   => fn($next) => ...,    // → get:/root/{root}/game/{id}/action（继承父 method）
 *     ''         => fn($next) => ...,    // → get:/root/{root}/game/{id}/（前缀匹配）
 *     '*'        => fn($next) => ...,    // → get:/root/{root}/game/{id}/*（通配符）
 *     ':'        => fn($next) => ...,    // 等效 ''
 * ]]);
 * ```
 * 智能子路由：commonBefore/commonAfter 自动包裹每个子 handler
 * ```
 * route('get:/prefix/',
 *     fn($next) => ...,                  // 公共前置
 *     ['sub' => fn($next) => ...,        // 子 handler
 *      'exe' => fn($next) => ...],
 *     fn($next) => ...,                  // 公共后置
 * );
 * ```
 * 多条路由匹配时，内部使用 middleware() 执行 handler，* 通配符支持阻断：
 * ```
 * route(['GET:/some/*' => fn($next) => 'wildcard',   // /some/action 匹配时，不调 $next 阻断后续
 *        'GET:/some/action' => fn($next) => 'action']);// 不会执行
 * route(['GET:/some/*' => fn($next) => $next(),       // 调 $next 放行
 *        'GET:/some/action' => fn($next) => 'action']);// 继续执行
 * ```
 * @param null|bool|string|array $match  匹配规则或路由映射数组
 *                                       true=开启延时模式
 *                                       null=清空已收集路由
 * @param array|callable         ...$fns 路由处理函数列表或子路由映射
 * @return ?string[]                     匹配成功的路由键数组，未匹配返回 null
 *                                       匹配结果转存到容器 #route.result
 */
function route(null|bool|string|array $match = null, array|callable ...$fns): ?array{
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
	$routes = is_array($match) ? $match : [$match => $fns];
	$normalized = [];
	foreach($routes as $key => $fn){
		$list = match (true) {
			!is_array($fn) => [$fn],
			!array_is_list($fn) => [$fn],
			default => $fn,
		};
		$subMap = null;
		$before = [];
		$after = [];
		foreach($list as $item){
			if($subMap) $after[] = $item;
			elseif(is_array($item) && !array_is_list($item)) $subMap = $item;
			else $before[] = $item;
		}
		if($subMap){
			[$method, $path] = !str_contains($key, ':') ? ['', $key] : explode(':', $key, 2);
			$pending = [[$subMap, $method, $path, $before, $after]];
			while($pending){
				[$curMap, $pMethod, $pPath, $pBefore, $pAfter] = array_shift($pending);
				foreach($curMap as $sm => $sfn){
					[$sm_method, $sm_uri] = !str_contains($sm, ':') ? [$pMethod, $sm] : explode(':', $sm, 2);
					if($sm_method === '') $sm_method = $pMethod;
					$accumPath = rtrim($pPath, '/') . ($sm_uri ? "/$sm_uri" : '');
					if(is_array($sfn) && !array_is_list($sfn)){
						$pending[] = [$sfn, $sm_method, $accumPath, $pBefore, $pAfter];
						continue;
					}
					$nestedMap = null;
					$innerBef = [];
					$innerAft = [];
					if(is_array($sfn)){
						foreach($sfn as $item){
							if($nestedMap) $innerAft[] = $item;
							elseif(is_array($item) && !array_is_list($item)) $nestedMap = $item;
							else $innerBef[] = $item;
						}
					}
					if($nestedMap) $pending[] = [$nestedMap, $sm_method, $accumPath, [...$pBefore, ...$innerBef], [...$innerAft, ...$pAfter]];
					else $normalized["$sm_method:$accumPath"] = is_array($sfn) ? [...$pBefore, ...$sfn, ...$pAfter] : [...$pBefore, $sfn, ...$pAfter];
				}
			}
			continue;
		}
		$normalized[$key] = $list;
	}
	$deferred = container('#route.deferred');
	if(is_array($deferred)){
		foreach($normalized as $m => $fn) $deferred[] = [$m, $fn];
		return container('#route.deferred', $deferred);
	}
	$handlers = [];
	$matchedKeys = [];
	$currentMethod = from('method', 'input');
	$params = from('params', 'input') ?? [];
	$reqSegments = $currentMethod === 'cli' ? [] : array_values(array_filter(explode('/', parse_url(from('uri', 'input'), PHP_URL_PATH) ?: '/')));
	foreach($normalized as $m => $fn){
		[$method, $uri] = !str_contains($m, ':') ? ['', $m] : explode(':', $m, 2);
		$method = strtolower($method);
		if($uri === '') $uri = '/';
		if($method === 'cli'){
			$routeArgs = args(substr($m, 4));
			$matched = true;
			foreach($routeArgs as $k => $v){
				if(!isset($params[$k]) || ($v !== '*' && $v !== true && $params[$k] !== $v)){
					$matched = false;
					break;
				}
			}
			if($matched){
				$handlers = [...$handlers, ...is_array($fn) ? $fn : [$fn]];
				$matchedKeys[] = $m;
			}
			continue;
		}
		if($method !== '*' && $method !== '' && $method !== $currentMethod) continue;
		$routeSegments = array_values(array_filter(explode('/', trim($uri))));
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
			if($p === '{' && ($route[-1] ?? '') === '}'){
				$param[trim($route, ':{}')] = $req;
				$reqIndex++;
				continue;
			}
			if($route !== $req) continue;
			$reqIndex++;
		}
		if($reqIndex === count($reqSegments) && ($isWildcard || $reqIndex === count($routeSegments))){
			$params = [...$params, ...$param];
			$handlers = [...$handlers, ...is_array($fn) ? $fn : [$fn]];
			$matchedKeys[] = $m;
		}
	}
	container('#in.params', $params);
	container('#route.result', $handlers ? middleware(...$handlers) : null);
	return $matchedKeys ?: null;
}
