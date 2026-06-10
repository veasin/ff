<?php
namespace nx\middleware\prefab;

use function nx\{container, from, output, i18n};

/**
 * HTTP Basic 认证中间件
 * 使用方式:
 * - 设置验证器: container("$prefix:validators", [fn($user, $pass) => $user])
 * - 使用中间件: middleware(basic(), $handler)
 * - 获取用户: container("$prefix:user")
 * @param string $prefix 前缀，默认 '#mw:auth'
 * @param string|null $realm  认证领域名称，默认 null 使用 i18n
 * @return callable 中间件函数
 */
function basic(string $prefix = '#mw:auth', ?string $realm = null): callable{
	return function($next) use ($prefix, $realm){
		if(container("$prefix:user")) return $next();
		$header = from('authorization', 'header') ?? '';
		if(!str_starts_with($header, 'Basic ')) return output(null, ['code' => 401, 'headers' => ['WWW-Authenticate' => "Basic realm=\"" . ($realm ?? i18n('#auth:realm_basic')) . "\""]]);
		$credentials = base64_decode(substr($header, 6));
		[$user, $pass] = array_pad(explode(':', $credentials, 2), 2, '');
		foreach(container("$prefix:validators") ?? [] as $validator){
			$result = $validator($user, $pass);
			if($result){
				container("$prefix:user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
