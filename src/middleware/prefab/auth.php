<?php
namespace nx\middleware\prefab;

use function nx\{container, from, output};

/**
 * HTTP Basic 认证中间件
 * 使用方式:
 * - 设置验证器: container("$prefix:validators", [fn($user, $pass) => true])
 * - 使用中间件: middleware(auth(), $handler)
 * - 获取用户: container("$prefix:user")
 * @param string $prefix 前缀，默认 '#mw:auth'
 * @param string $realm  认证领域名称，默认 'Protected'
 * @return callable 中间件函数
 */
function auth(string $prefix = '#mw:auth', string $realm = 'Protected'): callable{
	return function($next) use ($prefix, $realm){
		if(container("$prefix:user")) return $next();
		$header = from('authorization', 'header') ?? '';
		if(!str_starts_with($header, 'Basic ')) return output(null, 401, ['headers' => ['WWW-Authenticate' => "Basic realm=\"$realm\""]]);
		$credentials = base64_decode(substr($header, 6));
		[$user, $pass] = explode(':', $credentials, 2);
		foreach(container("$prefix:validators") ?? [] as $validator){
			if($validator($user, $pass)){
				container("$prefix:user", $user);
				return $next();
			}
		}
		return output(null, 403);
	};
}