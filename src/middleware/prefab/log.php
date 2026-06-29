<?php
namespace ff\middleware\prefab;
use function ff\{from, container, log as flog};

/**
 * 请求/响应日志中间件
 * 
 * 使用方式:
 * ```
 * middleware(log(), $handler);//基础使用，默认 info 级别
 * middleware(log('info'), $handler);//自定义日志级别
 * ```
 * 日志内容包含:
 * - method: 请求方法
 * - uri: 请求路径
 * - status: 响应状态码
 * - duration_ms: 执行时间 (毫秒)
 * - memory_kb: 内存使用 (KB)
 * 
 * @param string $level 日志级别，默认 'info'
 * @return callable 中间件函数
 */
function log(string $level = 'info'): callable{
	$start = microtime(true);
	$memStart = memory_get_usage();
	return function($next) use ($level, &$start, &$memStart){
		$request = [
			'method' => from('method', 'input'),
			'uri' => from('uri', 'input'),
		];
		$result = $next();
		$duration = round((microtime(true) - $start) * 1000, 2);
		$memory = round((memory_get_usage() - $memStart) / 1024, 2);
		$status = container('#out.response:code') ?? 200;
		flog([
			'method' => $request['method'],
			'uri' => $request['uri'],
			'status' => $status,
			'duration_ms' => $duration,
			'memory_kb' => $memory,
		], $level);
		return $result;
	};
}
