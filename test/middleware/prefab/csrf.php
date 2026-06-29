<?php
// csrf.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{container, middleware, test};
use function ff\middleware\prefab\csrf;

// 测试用例
test('csrf: 生成 token',
	function(){
		container('#mw:csrf:token', null);
		$result = middleware(csrf(), ['data' => 'test']);
		return strlen($result['_token'] ?? '');
	},
	64);
test('csrf: 验证成功',
	function(){
		container('#mw:csrf:token', 'test_token_123');
		container('#in.body', ['_token' => 'test_token_123', 'RAW' => '']);
		return middleware(csrf(verify: true), 'ok');
	},
	'ok');
test('csrf: 验证失败返回419',
	function(){
		container('#mw:csrf:token', 'test_token_123');
		container('#in.body', ['_token' => 'wrong_token', 'RAW' => '']);
		middleware(csrf(verify: true), 'ok');
		return container('#out.response.code');
	},
	419);
test();
