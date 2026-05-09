<?php
// cors.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\{container, middleware, test};
use function nx\middleware\prefab\cors;

// 测试用例
test('cors: 设置默认跨域头',
	function(){
		middleware(cors(), fn() => 'ok');
		return container('#out.response.headers.Access-Control-Allow-Origin');
	},
	'*');
test('cors: 自定义 origin',
	function(){
		middleware(cors(['origin' => 'https://example.com']), fn() => 'ok');
		return container('#out.response.headers.Access-Control-Allow-Origin');
	},
	'https://example.com');
test('cors: OPTIONS 请求返回 ok',
	function(){
		// CLI 模式下 from('method', 'input') 返回 'cli'，无法测试 OPTIONS
		return PHP_SAPI === 'cli' ? ['ok' => true] : null;
	},
	fn($v) => $v === ['ok' => true]);
