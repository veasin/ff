<?php
// rate.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\{middleware, test, container, from};
use function nx\middleware\prefab\rate;

test('rate: 允许通过请求',
	function(){
		$storage = [];
		container('#rate:storage', function($key = null, $value = null, $ttl = null) use (&$storage){
			if(func_num_args() <= 1) return $storage[$key] ?? null;
			$storage[$key] = $value;
		});
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		container('#in.input', null);
		return middleware(rate(60, 60, 'test'), 'ok');
	},
	'ok');

test('rate: 超出限制返回429',
	function(){
		$storage = [];
		container('#rate:storage', function($key = null, $value = null, $ttl = null) use (&$storage){
			if(func_num_args() <= 1) return $storage[$key] ?? null;
			$storage[$key] = $value;
		});
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		container('#in.input', null);
		middleware(rate(1, 60, 'test'), 'ok');
		middleware(rate(1, 60, 'test'), 'ok');
		return container('#out.response.code');
	},
	429);

test('rate: 不同IP独立计数',
	function(){
		$storage = [];
		container('#rate:storage', function($key = null, $value = null, $ttl = null) use (&$storage){
			if(func_num_args() <= 1) return $storage[$key] ?? null;
			$storage[$key] = $value;
		});
		container('#in.input', null);
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		middleware(rate(1, 60, 'ip'), 'ok');
		$_SERVER['REMOTE_ADDR'] = '127.0.0.2';
		return middleware(rate(1, 60, 'ip'), 'ok');
	},
	'ok');
test();
