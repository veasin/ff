<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, queue, test};

// 共享 db 连接
$cfg = 'qentry_' . uniqid();
container("#db.{$cfg}", ['dsn' => 'sqlite::memory:']);

test('queue函数存在', fn() => function_exists('ff\queue'), true);
test('db驱动函数存在', fn() => function_exists('ff\queue\db'), true);
test('null入参返回null', fn() => queue(null), null);

// ========== 配置解析 ==========
test('配置-内联配置', function() use ($cfg){
	$r = queue('icfg', 'val', ['driver' => 'db', 'table' => 'qt_icfg', 'config' => $cfg]);
	return $r === true;
}, true);
test('配置-内联消费', function() use ($cfg){
	return queue('icfg', fn($m) => $m === 'val', ['driver' => 'db', 'table' => 'qt_icfg', 'config' => $cfg]);
}, true);

test('配置-命名配置', function() use ($cfg){
	container('#queue.named_entry', ['driver' => 'db', 'table' => 'qt_nentry', 'config' => $cfg]);
	queue('nentry', 'nval', 'named_entry');
	return queue('nentry', fn($m) => $m === 'nval', 'named_entry');
}, true);

test('配置-默认driver', function() use ($cfg){
	container('#queue', ['driver' => 'db']);
	container('#queue/db', ['table' => 'qt_def', 'config' => $cfg]);
	$r = queue('defq', 'def');
	$check = queue('defq', fn($m) => $m === 'def');
	return $r === true && $check === true;
}, true);

// ========== 批量生产 ==========
test('批量生产', function() use ($cfg){
	container('#queue', ['driver' => 'db']);
	container('#queue/db', ['table' => 'qt_batch', 'config' => $cfg]);
	$r = queue(['ba' => 1, 'bb' => 'x']);
	return $r === ['ba' => true, 'bb' => true];
}, true);
test('批量消费', function() use ($cfg){
	$msgs = [];
	queue('ba', function($m) use (&$msgs){ $msgs[] = $m; return true; });
	queue('bb', function($m) use (&$msgs){ $msgs[] = $m; return true; });
	return $msgs === [1, 'x'];
}, true);

// ========== Driver 合并 ==========
test('配置-driver特定配置合并', function() use ($cfg){
	container('#queue/db', ['table' => 'qt_merge', 'config' => $cfg, 'db_type' => 'sqlite']);
	queue('merge', 'merged');
	return queue('merge', fn($m) => $m === 'merged');
}, true);

test();

