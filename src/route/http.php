<?php
declare(strict_types=1);
namespace ff\route;
use function ff\{from, container, middleware};
/**
 * HTTP 路由匹配器。
 * handler 签名 fn(array $normalized, array $params, string $uri, string $method): ?array
 */
function http(array $normalized, array $params, string $uri, string $method): ?array{
	$reqUri = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';
	$reqPath = ltrim($reqUri, '/');
	static $fnCompile = null, $routeCache = [], $cacheKey = null;
	$fnCompile ??= function(string $path): array{
		$segments = array_filter(explode('/', trim($path, '/')));
		$withNames = $withoutNames = '';
		$wildcard = false;
		foreach($segments as $seg){
			if($seg === '*'){
				$withNames .= '(?:/(?:.*))?';
				$withoutNames .= '(?:/(?:.*))?';
				$wildcard = true;
				continue;
			}
			if(($seg[0] ?? '') === '{' && ($seg[-1] ?? '') === '}'){
				$name = trim($seg, ':{}');
				$withNames .= '/(?P<' . $name . '>[^/]+)';
				$withoutNames .= '/(?:[^/]+)';
				continue;
			}
			$withNames .= '/' . preg_quote($seg, '#');
			$withoutNames .= '/' . preg_quote($seg, '#');
		}
		if(!$wildcard){
			if($withNames === '') $withNames = '/?';
			if($withoutNames === '') $withoutNames = '/?';
		}
		return [$withNames, $withoutNames];
	};
	$handlers = [];
	$matchedKeys = [];
	$currentCacheKey = md5(serialize(array_keys($normalized)));
	if($cacheKey !== $currentCacheKey){
		$cacheKey = $currentCacheKey;
		$rInfo = [];
		$combinedParts = [];
		foreach($normalized as $m => $fn){
			[$mMethod, $mUri] = str_contains($m, ':') ? explode(':', $m, 2) : ['', $m];
			$mMethod = strtolower($mMethod);
			if($mUri === '') $mUri = '/';
			$path = trim($mUri, '/');
			if(!str_contains($path, '{') && !str_contains($path, '*')){
				$rInfo[$m] = ['method' => $mMethod, 'exact' => true, 'path' => $path];
			}else{
				[$with, $without] = $fnCompile($mUri);
				$rInfo[$m] = ['method' => $mMethod, 'exact' => false, 'regex' => '#^' . $with . '$#'];
				$combinedParts[] = '(' . $without . ')';
			}
		}
		$combinedRegex = $combinedParts ? '#^(?:' . implode('|', $combinedParts) . ')$#' : null;
		$routeCache[$currentCacheKey] = ['info' => $rInfo, 'combined' => $combinedRegex];
	}
	$cache = $routeCache[$currentCacheKey];
	$rInfo = $cache['info'];
	if($cache['combined'] && preg_match($cache['combined'], $reqUri)){
		foreach($normalized as $m => $fn){
			$info = $rInfo[$m] ?? null;
			if($info === null) continue;
			$mMethod = $info['method'];
			if($mMethod !== '*' && $mMethod !== '' && $mMethod !== $method) continue;
			if($info['exact']){
				if($info['path'] === $reqPath){
					foreach((array)$fn as $f) $handlers[] = $f;
					$matchedKeys[] = $m;
				}
				continue;
			}
			if(preg_match($info['regex'], $reqUri, $rm)){
				foreach($rm as $k => $v) if(!is_int($k)) $params[$k] = $v;
				foreach((array)$fn as $f) $handlers[] = $f;
				$matchedKeys[] = $m;
			}
		}
	}else{
		foreach($normalized as $m => $fn){
			$info = $rInfo[$m] ?? null;
			if($info === null) continue;
			$mMethod = $info['method'];
			if($mMethod !== '*' && $mMethod !== '' && $mMethod !== $method) continue;
			if($info['exact'] && $info['path'] === $reqPath){
				foreach((array)$fn as $f) $handlers[] = $f;
				$matchedKeys[] = $m;
			}
		}
	}
	return ['handlers' => $handlers, 'keys' => $matchedKeys, 'params' => $params];
}
