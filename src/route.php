<?php
declare(strict_types=1);
namespace ff;
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
 * 智能子路由：int-key callable 为中间件（累积栈），string-key 为子路由
 * ```
 * // 前置中间件 + inline 子路由 + 后置中间件
 * route(['get:/prefix/'=>[
 *     fn($next) => ...,                  // 前置（所有子路由之前）
 *     'post:run' => fn($next) => ...,    // 子路由
 *     'get:exe'  => fn($next) => ...,    // 子路由
 *     fn($next) => ...,                  // 后置（所有子路由之后）
 * ]]);
 * ```
 * 也兼容旧格式 `['sub'=>fn]` 包裹子映射：
 * ```
 * route('get:/prefix/',
 *     fn($next) => ...,
 *     ['post:run' => fn($next) => ...],
 *     fn($next) => ...,
 * );
 * ```
 * 中间件按出现位置决定归属：每个子路由获取到它位置为止的累积栈作为 before，
 * 之后的所有中间件作为 after。尾部中间件追加给全体子路由的 after。
 * ```
 * // [b1, k1:fn1, b2, k2:fn2, a]
 * // k1 → [b1, fn1, b2, a]     k2 → [b1, b2, fn2, a]
 * ```
 * 清单数组 `[fn, fn]` 自动展平入累积栈：
 * ```
 * route(['get:/prefix/'=>[
 *     fn($next) => ...,                  // 前置
 *     [fn($next) => ..., fn($next) => ...],// 展平为两个前置
 *     'run' => fn($next) => ...,         // 子路由
 *     fn($next) => ...,                  // 后置
 * ]]);
 * // → run 的 handler 链: [前置, fn1, fn2, handler, 后置]
 * ```
 * 嵌套子路由中每层均支持同样的栈机制：
 * ```
 * route(['get:/level1/'=>[
 *     fn($next) => ...,                  // 外层前置
 *     'level2/' => [
 *         [fn($next) => ..., fn($next) => ...],// 内层前置组
 *         'deep' => fn($next) => ...,    // 最终 handler
 *         fn($next) => ...,              // 内层后置
 *     ],
 *     fn($next) => ...,                  // 外层后置
 * ]]);
 * // → deep: [外前置, 内前置A, 内前置B, handler, 内后置, 外后置]
 * ```
 * 外层数组的 int-key callable（bare function）自动视为 '*' 通配符，等效于写 `'*' => fn`：
 * ```
 * route([
 *     'get:/list' => fn($next) => ...,       // 普通路由
 *     fn($next) => basic($next),              // 等效 '*' => fn($next) => basic($next)
 *     'post:/create' => fn($next) => ...,
 * ]);
 * // → basic 包裹其后所有匹配的路由
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
		if(is_int($key)){
			if(isset($normalized['*'])){
				$normalized['*'] = [...$normalized['*'], ...(!is_array($fn) ? [$fn] : $fn)];
				continue;
			}
			$key = '*';
		}
		$list = !is_array($fn) ? [$fn] : $fn;
		$stack = [];
		$subs = [];
		foreach($list as $k => $v){
			if(is_string($k)) $subs[$k] = ['h' => $v, 'p' => count($stack)];
			elseif(is_array($v)){
				if(!array_is_list($v)) foreach($v as $sk => $sv) $subs[$sk] = ['h' => $sv, 'p' => count($stack)];
				else foreach($v as $item) if(is_callable($item)) $stack[] = $item;
			}
			elseif(is_callable($v)) $stack[] = $v;
		}
		if(!$subs){
			$normalized[$key] = $list;
			continue;
		}
		[$method, $path] = !str_contains($key, ':') ? ['', $key] : explode(':', $key, 2);
		$pending = [];
		foreach($subs as $sk => $e) $pending[] = [$sk, $e['h'], $method, $path, array_slice($stack, 0, $e['p']), array_slice($stack, $e['p'])];
		while($pending){
			[$sm, $sfn, $pM, $pP, $pBef, $pAft] = array_shift($pending);
			[$sm_m, $sm_u] = !str_contains($sm, ':') ? [$pM, $sm] : explode(':', $sm, 2);
			if($sm_m === '') $sm_m = $pM;
			$accPath = rtrim($pP, '/') . ($sm_u ? "/$sm_u" : '');
			if(is_array($sfn)){
				$is = [];
				$iss = [];
				foreach($sfn as $k2 => $v2){
					if(is_string($k2)) $iss[$k2] = ['h' => $v2, 'p' => count($is)];
					elseif(is_array($v2)){
						if(!array_is_list($v2)) foreach($v2 as $sk => $sv) $iss[$sk] = ['h' => $sv, 'p' => count($is)];
						else foreach($v2 as $item) if(is_callable($item)) $is[] = $item;
					}
					elseif(is_callable($v2)) $is[] = $v2;
				}
				if($iss){
					foreach($iss as $ik => $ie) $pending[] = [$ik, $ie['h'], $sm_m, $accPath, [...$pBef, ...array_slice($is, 0, $ie['p'])], [...array_slice($is, $ie['p']), ...$pAft]];
					continue;
				}
				$normalized["$sm_m:$accPath"] = [...$pBef, ...$is, ...$pAft];
			}
			else $normalized["$sm_m:$accPath"] = [...$pBef, $sfn, ...$pAft];
		}
	}
	$deferred = container('#route.deferred');
	if(is_array($deferred)){
		foreach($normalized as $m => $fn) $deferred[] = [$m, $fn];
		return container('#route.deferred', $deferred);
	}
	$handlers = [];
	$matchedKeys = [];
	$currentMethod = from('method', 'input');
	$params = from(null, 'params');
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
