<?php
// auth.php 测试
include __DIR__ . "/../../_boot.php";

use function nx\{container, middleware, test};
use function nx\middleware\prefab\auth;

// 测试用例
test('auth: 未认证返回401',
	function(){
		container('#mw:auth:validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456']);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = '';
		middleware(auth(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	401);
test('auth: 认证成功返回结果',
	function(){
		container('#mw:auth:validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456']);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:123456');
		return middleware(auth(), fn($next) => 'ok');
	},
	'ok');
test('auth: 密码错误返回403',
	function(){
		container('#mw:auth:validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456']);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:wrong');
		middleware(auth(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	403);
test();
