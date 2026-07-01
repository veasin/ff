<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\queue\redis;

test('redis驱动函数存在', fn() => function_exists('ff\queue\redis'), true);

// 尝试连接 Redis，仅当可用时运行功能测试
$available = false;
try{
	$c = new \Redis();
	$available = $c->connect('127.0.0.1', 6379, 1.0);
}catch(\Exception $e){}
$pfx = 'ff_test_' . getmypid() . ':';

if(!$available){
	test('Redis未运行-跳过功能测试', true, true);
	test();
	exit;
}

container('#redis.default', ['host' => '127.0.0.1', 'port' => 6379, 'prefix' => $pfx]);

// ========== 生产 ==========
test('Redis生产-字符串', function() use ($pfx){
	return redis('rs', 'hello');
}, true);
test('Redis生产-数组', function() use ($pfx){
	return redis('rs', [1, 2, 3]);
}, true);

// ========== 消费 ack ==========
test('Redis消费-ack', function() use ($pfx){
	$got = null;
	$r = redis('rs', function($m) use (&$got){ $got = $m; return true; });
	return $r === true && $got === 'hello';
}, true);
test('Redis消费-ack后空', function() use ($pfx){
	return redis('rs', fn($m) => true);
}, false);

// ========== 消费 nack ==========
test('Redis消费-nack requeue', function() use ($pfx){
	redis('rn', 'nack-val');
	$r1 = redis('rn', fn($m) => false);
	$r2 = redis('rn', fn($m) => $m === 'nack-val');
	return $r1 === true && $r2 === true;
}, true);

// ========== 消费 discard ==========
test('Redis消费-discard', function() use ($pfx){
	redis('rd', 'discard');
	$r1 = redis('rd', fn($m) => null);
	return $r1 === true;
}, true);
test('Redis消费-discard后空', function() use ($pfx){
	return redis('rd', fn($m) => true);
}, false);

// ========== 消费 retry ==========
test('Redis消费-retry', function() use ($pfx){
	redis('rr', 'retry');
	$r1 = redis('rr', fn($m) => 1);
	sleep(1);
	$r2 = redis('rr', fn($m) => $m === 'retry');
	return $r1 === true && $r2 === true;
}, true);

// ========== 空队列 ==========
test('Redis空队列', function() use ($pfx){
	return redis('re', fn($m) => true);
}, false);

// ========== 清理 ==========
$clean = new \Redis();
$clean->connect('127.0.0.1', 6379);
$keys = $clean->keys($pfx . '*');
foreach($keys ?? [] as $k) $clean->del($k);

test();
