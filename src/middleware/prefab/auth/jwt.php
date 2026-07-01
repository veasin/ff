<?php
namespace ff\middleware\prefab;

use function ff\{container, from, output, i18n};

/**
 * JWT 认证中间件
 * 使用方式:
 * ```
 * container('#mw/auth/secret', 'your-secret-key');//设置密钥
 * container('#mw/auth/validators', [fn($payload) => $user]);//设置验证器
 * middleware(jwt(), $handler);//使用中间件
 * container('#mw/auth/user');//获取认证用户
 * container('#mw/auth/payload');//获取 payload
 * ```
 * @param string $prefix 前缀，默认 '#mw/auth'
 * @param string $algo   算法，默认 'HS256'
 * @return callable 中间件函数
 */
function jwt(string $prefix = '#mw/auth', string $algo = 'HS256'): callable{
	return function($next) use ($prefix, $algo){
		if(container("$prefix/user")) return $next();
		$header = from('authorization', 'header') ?? '';
		if(!str_starts_with($header, 'Bearer ')) return output(null, ['code' => 401, 'headers' => ['WWW-Authenticate' => 'Bearer realm="' . i18n('#auth:realm_jwt') . '"']]);
		$token = substr($header, 7);
		$parts = explode('.', $token);
		if(count($parts) !== 3) return output(null, 401);
		[$headerB64, $payloadB64, $sigB64] = $parts;
		$payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
		$sig = base64_decode(strtr($sigB64, '-_', '+/'));
		$secret = container("$prefix/secret") ?? '';
		$expectedSig = hash_hmac($algo === 'HS256' ? 'sha256' : 'sha512', "$headerB64.$payloadB64", $secret, true);
		if(!hash_equals($expectedSig, $sig)) return output(null, 403);
		foreach(container("$prefix/validators") ?? [] as $validator){
			$result = $validator($payload);
			if($result){
				container("$prefix/user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
