<?php
// apikey.php 测试
include __DIR__ . "/../../../../vendor/autoload.php";

use function ff\{container, middleware, test};
use function ff\middleware\prefab\apikey;

test('apikey: 无 apiKey 返回401',
	function(){
		container('#mw/auth/validators', [fn($apiKey) => $apiKey === 'test-api-key' ? 'user1' : null]);
		container('#mw/auth/user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		middleware(apikey(), 'ok');
		return container('#out.response.code');
	},
	401);

test('apikey: 从 header 认证成功',
	function(){
		container('#mw/auth/validators', [fn($apiKey) => $apiKey === 'test-api-key' ? 'user1' : null]);
		container('#mw/auth/user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_X_API_KEY'] = 'test-api-key';
		return middleware(apikey('#mw/auth', 'x-api-key'), 'ok');
	},
	'ok');

test('apikey: 从 query 认证成功',
	function(){
		container('#mw/auth/validators', [fn($apiKey) => $apiKey === 'test-api-key' ? 'user1' : null]);
		container('#mw/auth/user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['api_key'] = 'test-api-key';
		return middleware(apikey('#mw/auth', 'x-api-key'), 'ok');
	},
	'ok');

test('apikey: 无效 apiKey 返回403',
	function(){
		container('#mw/auth/validators', [fn($apiKey) => $apiKey === 'test-api-key' ? 'user1' : null]);
		container('#mw/auth/user', null);
		container('#out.response', null);
		container('#in.headers', null);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_X_API_KEY'] = 'invalid-key';
		middleware(apikey('#mw/auth', 'x-api-key'), 'ok');
		return container('#out.response.code');
	},
	403);
test();
