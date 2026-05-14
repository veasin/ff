<?php
// jwt.php 测试
include __DIR__ . "/../../../../vendor/autoload.php";

use function nx\{container, middleware, test};
use function nx\middleware\prefab\jwt;

function createJwt(string $secret, array $payload, string $algo = 'HS256'): string{
	$header = ['typ' => 'JWT', 'alg' => $algo];
	$headerB64 = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
	$payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
	$sig = hash_hmac($algo === 'HS256' ? 'sha256' : 'sha512', "$headerB64.$payloadB64", $secret, true);
	$sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
	return "$headerB64.$payloadB64.$sigB64";
}

test('jwt: 无 token 返回401',
	function(){
		container('#mw:auth:secret', 'test-secret');
		container('#mw:auth:validators', [fn($payload) => ($payload['sub'] ?? null) === 'user1' ? 'user1' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		middleware(jwt(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	401);

test('jwt: 认证成功返回结果',
	function(){
		$token = createJwt('test-secret', ['sub' => 'user1', 'exp' => time() + 3600]);
		container('#mw:auth:secret', 'test-secret');
		container('#mw:auth:validators', [fn($payload) => ($payload['sub'] ?? null) === 'user1' ? 'user1' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		return middleware(jwt(), fn($next) => 'ok');
	},
	'ok');

test('jwt: 签名错误返回403',
	function(){
		$token = createJwt('wrong-secret', ['sub' => 'user1', 'exp' => time() + 3600]);
		container('#mw:auth:secret', 'test-secret');
		container('#mw:auth:validators', [fn($payload) => ($payload['sub'] ?? null) === 'user1' ? 'user1' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		middleware(jwt(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	403);
test();
