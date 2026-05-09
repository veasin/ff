<?php
namespace nx\middleware\prefab;
use function nx\{from, output, container};

/**
 * 接口限流中间件
 * 
 * 使用方式:
 * - 默认限制: middleware(rate(), $handler) - 每分钟 60 次
 * - 自定义限制: middleware(rate(100, 60), $handler) - 每分钟 100 次
 * - 命名限流: middleware(rate(100, 60, 'api'), $handler)
 * 
 * 存储方式:
 * - 默认使用 APCu: 需要 APCu 扩展
 * - 自定义存储: container('#rate:storage', fn($key, $window) => [$timestamp, ...])
 * - 或 container('#rate:storage', fn($key, $value, $ttl) => ...)
 *
 * @param int    $maxRequests  最大请求次数，默认 60
 * @param int    $windowSeconds 时间窗口（秒），默认 60
 * @param string $key         限流标识符，默认 'rate'
 * @return callable 中间件函数
 */
function rate(int $maxRequests = 60, int $windowSeconds = 60, string $key = 'rate'): callable{
	return function($next) use ($maxRequests, $windowSeconds, $key){
		$ip = $_SERVER['REMOTE_ADDR'] ?? from('remote_addr', 'input') ?? 'unknown';
		$route = from('uri', 'input') ?? '';
		$cacheKey = "$key:$ip:$route";
		$storage = container('#rate:storage');
		$timestamps = $storage ? $storage($cacheKey) : null;
		if($timestamps === null){
			$timestamps = [];
			if(function_exists('apcu_fetch')){
				$timestamps = apcu_fetch($cacheKey, $success) ?: [];
			}
		}
		$now = time();
		$timestamps = array_filter($timestamps, fn($t) => $t > $now - $windowSeconds);
		if(count($timestamps) >= $maxRequests) return output(null, 429, ['message' => 'Too many requests.']);
		$timestamps[] = $now;
		if($storage){
			$storage($cacheKey, $timestamps, $windowSeconds);
		}elseif(function_exists('apcu_store')){
			apcu_store($cacheKey, $timestamps, $windowSeconds);
		}
		return $next();
	};
}
