<?php
// token.php 测试
include __DIR__ . "/../../../../vendor/autoload.php";

use function nx\{container, middleware, test};
use function nx\middleware\prefab\token;

test('token: 无 token 返回401',
	function(){
		container('#mw:auth:validators', [fn($token) => $token === 'valid-token' ? 'user1' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		middleware(token(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	401);

test('token: 认证成功返回结果',
	function(){
		container('#mw:auth:validators', [fn($token) => $token === 'valid-token' ? 'user1' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token';
		return middleware(token(), fn($next) => 'ok');
	},
	'ok');

test('token: 无效 token 返回403',
	function(){
		container('#mw:auth:validators', [fn($token) => $token === 'valid-token' ? 'user1' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid-token';
		middleware(token(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	403);
