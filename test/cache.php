<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{cache, test};

test('无参返回 null', cache(), null);
test('单个闭包直接返回', cache(fn($next) => 'value'), 'value');
test('两级链式调用', cache(fn($next) => $next(), fn($next) => 'second'), 'second');
test('全部返回 null', cache(fn($next) => $next(), fn($next) => $next(), fn($next) => null), null);
test('首个返回值短路', cache(fn($next) => 'immediate', fn($next) => 'never'), 'immediate');
test('中间 null 穿透到下一层', cache(fn($next) => $next(), fn($next) => null, fn($next) => 'final'), 'final');
test('配合 ?? 设默认值', cache(fn($next) => $next(), fn($next) => null) ?? 'default', 'default');
test('嵌套调用', cache(fn($next) => 'outer_' . ($next ? $next() : 'no-next')), 'outer_');
test('非闭包值直接返回', cache('fresh'), 'fresh');
test('非闭包兜底值', cache(fn($next) => $next(), 'fallback'), 'fallback');
test('闭包短路后忽略非闭包', cache(fn($next) => 'hit', 'ignored'), 'hit');
test('空字符串兜底', cache(fn($next) => $next(), ''), '');
test('null 兜底', cache(fn($next) => $next(), null), null);

$log = [];
$result = cache(
	function($next) use (&$log){ $log[] = 'first'; return $next(); },
	function($next) use (&$log){ $log[] = 'second'; return $next(); },
	fn($next) => 'final'
);
test('链式执行顺序', ['result' => $result, 'log' => $log], ['result' => 'final', 'log' => ['first', 'second']]);

$store = [];
$result = cache(
	function($next) use (&$store){ $v = $next(); if($v !== null) $store['cached'] = $v; return $v; },
	fn($next) => 'computed'
);
test('写入缓存逻辑', $result, 'computed');
test('写入缓存验证', $store['cached'] ?? null, 'computed');

test();
