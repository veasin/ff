<?php
namespace ff\middleware\prefab;

use function ff\{container, from, output, i18n};

/**
 * API Key 认证中间件
 * 使用方式:
 * ```
 * container('#mw/auth/validators', [fn($apiKey) => $user]);//设置验证器
 * middleware(apikey(), $handler);//使用中间件
 * container('#mw/auth/user');//获取认证用户
 * ```
 * 从 header 或 query 参数获取 API Key
 * @param string $prefix 前缀，默认 '#mw/auth'
 * @param string $headerName header 名称，默认 'X-API-Key'
 * @param string $queryName  query 参数名，默认 'api_key'
 * @return callable 中间件函数
 */
function apikey(string $prefix = '#mw/auth', string $headerName = 'X-API-Key', string $queryName = 'api_key'): callable{
	$headerName = strtolower(str_replace('_', '-', $headerName));
	return function($next) use ($prefix, $headerName, $queryName){
		if(container("$prefix/user")) return $next();
		$apiKey = from($headerName, 'header') ?? from($queryName, 'query');
		if(!$apiKey) return output(null, ['code' => 401, 'headers' => ['X-API-Key' => i18n('#ff.auth.apikey_required')]]);
		foreach(container("$prefix/validators") ?? [] as $validator){
			$result = $validator($apiKey);
			if($result){
				container("$prefix/user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
