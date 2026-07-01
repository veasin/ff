<?php
namespace ff\middleware\prefab;

use function ff\{container, from, output, i18n};

/**
 * CSRF 防护中间件
 * 使用方式:
 * ```
 * middleware(csrf(), $handler);//生成 token，自动在响应中添加 _token
 * middleware(csrf(verify: true), $handler);//验证请求中的 token
 * ```
 * 行为:
 * - 不验证时: 自动生成 token 并注入响应
 * - 验证时: 检查请求中的 _token 或 X-CSRF-Token header
 * - token 存储在 container('#mw/csrf/token')
 * @param bool $verify 是否验证 token，默认 false
 * @return callable 中间件函数
 */
function csrf(bool $verify = false): callable{
	return function($next) use ($verify){
		$token = from('_token', 'body') ?? from('X-CSRF-Token', 'header');
		$sessionToken = container('#mw/csrf/token');
		if($verify && ($token !== $sessionToken)) return output(null, ['code' => 419, 'message' => i18n('#error:csrf_mismatch')]);
		$newToken = $sessionToken ?? bin2hex(random_bytes(32));
		container('#mw/csrf/token', $newToken);
		$result = $next();
		if(is_array($result)) $result['_token'] = $newToken;
		elseif(is_object($result)) $result->token = $newToken;
		return $result;
	};
}
