<?php
namespace nx\middleware\prefab;
use function nx\{from, output};

/**
 * 统一 JSON 响应格式中间件
 * 
 * 使用方式:
 * ```
 * middleware(json(), $handler);//默认格式
 * middleware(json(pretty: true), $handler);//格式化输出
 * ```
 * 行为:
 * - 统一设置 Content-Type 为 application/json
 * - 自动处理数组和 JSON 字符串
 * 
 * @param bool $pretty 是否格式化输出，默认 false
 * @return callable 中间件函数
 */
function json(bool $pretty = false): callable{
	return function($next) use ($pretty){
		$result = $next();
		if($result === null) return null;
		$result = is_array($result) ? $result : (json_decode($result, true) ?? $result);
		output($result, ['type' => 'json', 'pretty' => $pretty, 'headers' => ['Content-Type' => 'application/json; charset=UTF-8']]);
		return $result;
	};
}
