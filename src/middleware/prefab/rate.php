<?php
namespace ff\middleware\prefab;
use function ff\{from, output, container, i18n};
use function ff\cache\apcu;

/**
 * 接口限流中间件
 * 
 * 使用方式:
 * ```
 * middleware(rate(), $handler);//默认限制，每分钟 60 次
 * middleware(rate(100, 60), $handler);//自定义限制，每分钟 100 次
 * middleware(rate(100, 60, 'api'), $handler);//命名限流
 * ```
 * 存储方式:
 * - 默认使用 APCu: 通过框架 apcu() 函数
 * - 自定义存储: container('#mw/rate/storage', fn($key, $value, $ttl) => ...)
 *   fn($key)          — 读取
 *   fn($key, $timestamps, $ttl) — 写入
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
		$storage = container('#mw/rate/storage');
		$timestamps = $storage ? $storage($cacheKey) : null;
		if($timestamps === null) $timestamps = apcu($cacheKey) ?? [];
		$now = time();
		$timestamps = array_filter($timestamps, fn($t) => $t > $now - $windowSeconds);
		if(count($timestamps) >= $maxRequests) return output(null, ['code' => 429, 'message' => i18n('#error:rate_limit')]);
		$timestamps[] = $now;
		if($storage) $storage($cacheKey, $timestamps, $windowSeconds);
		else apcu($cacheKey, $timestamps, $windowSeconds);
		return $next();
	};
}
