<?php
namespace nx\middleware\prefab;
use function nx\{from, container, output};

/**
 * Gzip 响应压缩中间件
 * 
 * 使用方式:
 * ```
 * middleware(gzip(), $handler);//默认压缩级别 6
 * middleware(gzip(9), $handler);//自定义级别 1-9，越高压缩越好但越慢
 * ```
 * 行为:
 * - 自动检测客户端 Accept-Encoding 请求头
 * - 仅当压缩后比原始内容更小时才启用压缩
 * - 跳过 null、array、OPTIONS 请求
 * 
 * @param int $level 压缩级别 1-9，默认 6
 * @return callable 中间件函数
 */
function gzip(int $level = 6): callable{
	return function($next) use ($level){
		$result = $next();
		if(!function_exists('gzencode')) return $result;
		if($result === null || is_array($result) || from('method', 'input') === 'OPTIONS') return $result;
		$accept = from('accept-encoding', 'header') ?? '';
		if(!str_contains($accept, 'gzip')) return $result;
		$body = is_string($result) ? $result : json_encode($result);
		$compressed = gzencode($body, $level);
		if(strlen($body) <= strlen($compressed)) return $result;
		output(null, ['code' => 200, 'headers' => ['Content-Encoding' => 'gzip']]);
		return $compressed;
	};
}
