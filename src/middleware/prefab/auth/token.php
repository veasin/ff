<?php
namespace nx\middleware\prefab;

use function nx\{container, from, output, i18n};

/**
 * Token 认证中间件
 * 使用方式:
 * ```
 * container('#mw:auth:validators', [fn($token) => $user]);//设置验证器
 * middleware(token(), $handler);//使用中间件
 * container('#mw:auth:user');//获取认证用户
 * ```
 * @param string $prefix 前缀，默认 '#mw:auth'
 * @param string $headerName header 名称，默认 'Authorization'
 * @return callable 中间件函数
 */
function token(string $prefix = '#mw:auth', string $headerName = 'Authorization'): callable{
	$headerName = strtolower(str_replace('-', '_', $headerName));
	return function($next) use ($prefix, $headerName){
		if(container("$prefix:user")) return $next();
		$rawToken = from($headerName, 'header') ?? from('token', 'query');
		$token = str_starts_with($rawToken, 'Bearer ') ? substr($rawToken, 7) : $rawToken;
		if(!$token) return output(null, ['code' => 401, 'headers' => ['WWW-Authenticate' => 'Bearer realm="' . i18n('#auth:realm_token') . '"']]);
		foreach(container("$prefix:validators") ?? [] as $validator){
			$result = $validator($token);
			if($result){
				container("$prefix:user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
