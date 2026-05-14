<?php
// log.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\{middleware, test, container, log as nxlog};
use function nx\middleware\prefab\log as mw_log;

$logged = [];
container('#log', ['fn' => function($level, $message, $context) use (&$logged){
	$logged[] = ['level' => $level, 'message' => $message, 'context' => $context];
}]);

test('log: 记录请求方法',
	function() use (&$logged){
		$logged = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		container('#out.response', null);
		container('#in.input', null);
		middleware(mw_log(), fn($next) => 'ok');
		return $logged[0]['message']['method'] ?? null;
	},
	'cli');

test('log: 记录响应状态',
	function() use (&$logged){
		$logged = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		container('#out.response', ['code' => 200, 'body' => 'ok']);
		container('#in.input', null);
		middleware(mw_log(), fn($next) => 'ok');
		return $logged[0]['message']['status'] ?? null;
	},
	200);

test('log: 记录执行时间',
	function() use (&$logged){
		$logged = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		container('#out.response', null);
		container('#in.input', null);
		middleware(mw_log(), fn($next) => 'ok');
		return $logged[0]['message']['duration_ms'] ?? null;
	},
	fn($v) => $v >= 0);

test('log: 使用指定级别',
	function() use (&$logged){
		$logged = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		container('#out.response', null);
		container('#in.input', null);
		middleware(mw_log('warning'), fn($next) => 'ok');
		return $logged[0]['level'] ?? null;
	},
	'warning');
test();
