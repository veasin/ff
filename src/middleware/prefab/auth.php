<?php
namespace ff\middleware\prefab;

use function ff\{container, from, output, i18n};

/**
 * HTTP Basic 认证中间件
 * 使用方式:
 * ```
 * container('#mw:auth:validators', [fn($user, $pass) => true]);//设置验证器
 * middleware(auth(), $handler);//使用中间件
 * container('#mw:auth:user');//获取认证用户
 * ```
 * @param string $prefix 前缀，默认 '#mw:auth'
 * @param string|null $realm  认证领域名称，默认 null 使用 i18n
 * @return callable 中间件函数
 */
function auth(string $prefix = '#mw:auth', ?string $realm = null): callable{
	return function($next) use ($prefix, $realm){
		if(container("$prefix:user")) return $next();
		$header = from('authorization', 'header') ?? '';
		if(!str_starts_with($header, 'Basic ')) return output(null, ['code' => 401, 'headers' => ['WWW-Authenticate' => "Basic realm=\"" . ($realm ?? i18n('#auth:realm_basic')) . "\""]]);
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