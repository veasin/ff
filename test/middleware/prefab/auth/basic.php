<?php
// basic.php 测试
include __DIR__ . "/../../../../vendor/autoload.php";

use function nx\{container, middleware, test};
use function nx\middleware\prefab\basic;

test('auth_basic: 未认证返回401',
	function(){
		container('#mw:auth:validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456' ? 'admin' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = '';
		middleware(basic(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	401);

test('auth_basic: 认证成功返回结果',
	function(){
		container('#mw:auth:validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456' ? 'admin' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:123456');
		return middleware(basic(), fn($next) => 'ok');
	},
	'ok');

test('auth_basic: 密码错误返回403',
	function(){
		container('#mw:auth:validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456' ? 'admin' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:wrong');
		middleware(basic(), fn($next) => 'ok');
		return container('#out.response.code');
	},
	403);

test('auth_basic: 密码含冒号正确解析',
	function(){
		container('#mw:auth:validators', [fn($user, $pass) => $user === 'admin' && $pass === 'pass:word:123' ? 'admin' : null]);
		container('#mw:auth:user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic YWRtaW46cGFzczp3b3JkOjEyMw==';
		return middleware(basic(), fn($next) => 'ok');
	},
	'ok');
test();
