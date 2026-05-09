<?php
namespace nx;
/**
 * 路由匹配，支持 CLI 和 Web 两种模式。
 * 路由格式: method:/path，支持 :param 和 {param} 参数，* 通配符
 * - 行尾 /* 匹配剩余所有路径段
 * - 中间 * 匹配单个路径段
 * 依次遍历每条路由，按配置顺序收集所有匹配的 handler 执行。
 * @param string|array $match  匹配规则或路由映射数组
 * @param callable     ...$fns 路由处理函数列表
 * @return mixed
 */
function route(string|array $match, callable ...$fns): mixed{
	$handlers = [];
	$currentMethod = from('method', 'input');
	$params = from('params', 'input') ?? [];
	$reqSegments = $currentMethod === 'cli' ? [] : explode('/', parse_url(from('uri', 'input'), PHP_URL_PATH) ?: '/')
			|> array_filter(...)
			|> array_values(...);
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
		$routeSegments = $uri
				|> trim(...)
				|> (fn($x) => explode('/', $x))
				|> array_filter(...)
				|> array_values(...);
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
	return $handlers ? hump(...$handlers) : null;
}
