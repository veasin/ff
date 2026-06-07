<?php
namespace nx\middleware\prefab;

use function nx\{container, from, output, i18n};

/**
 * API Key 认证中间件
 * 使用方式:
 * - 设置验证器: container("$prefix:validators", [fn($apiKey) => $user])
 * - 使用中间件: middleware(apikey(), $handler)
 * - 获取用户: container("$prefix:user")
 * - 从 header 或 query 参数获取 API Key
 * @param string $prefix 前缀，默认 '#mw:auth'
 * @param string $headerName header 名称，默认 'X-API-Key'
 * @param string $queryName  query 参数名，默认 'api_key'
 * @return callable 中间件函数
 */
function apikey(string $prefix = '#mw:auth', string $headerName = 'X-API-Key', string $queryName = 'api_key'): callable{
	$headerName = strtolower(str_replace('_', '-', $headerName));
	return function($next) use ($prefix, $headerName, $queryName){
		if(container("$prefix:user")) return $next();
		$apiKey = from($headerName, 'header') ?? from($queryName, 'query');
		if(!$apiKey) return output(null, 401, ['headers' => ['X-API-Key' => i18n('#auth:apikey_required')]]);
		foreach(container("$prefix:validators") ?? [] as $validator){
			$result = $validator($apiKey);
			if($result){
				container("$prefix:user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
