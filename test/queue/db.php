<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\queue\db;

$cfg = 'qdb_' . uniqid();
container("#db.{$cfg}", ['dsn' => 'sqlite::memory:']);

// ========== 生产 ==========
test('生产-字符串', function() use ($cfg){
	return db('s', 'hello', ['table' => 't1', 'config' => $cfg]);
}, true);
test('生产-数组', function() use ($cfg){
	return db('s', [1, 2, 3], ['table' => 't1', 'config' => $cfg]);
}, true);
test('生产-数字', function() use ($cfg){
	return db('s', 42, ['table' => 't1', 'config' => $cfg]);
}, true);
test('生产-bool', function() use ($cfg){
	return db('s', true, ['table' => 't1', 'config' => $cfg]);
}, true);
test('生产-null值', function() use ($cfg){
	return db('s', null, ['table' => 't1', 'config' => $cfg]);
}, true);

// ========== 消费 ack ==========
test('消费-ack正确返回', function() use ($cfg){
	db('ack1', 'ack-msg', ['table' => 't2', 'config' => $cfg]);
	$got = null;
	$r = db('ack1', function($m) use (&$got){ $got = $m; return true; }, ['table' => 't2', 'config' => $cfg]);
	return $r === true && $got === 'ack-msg';
}, true);
test('消费-ack后消息删除', function() use ($cfg){
	return db('ack1', fn($m) => true, ['table' => 't2', 'config' => $cfg]);
}, false);

// ========== 消费 nack ==========
test('消费-nack重新入队', function() use ($cfg){
	db('nack1', 'nack-me', ['table' => 't3', 'config' => $cfg]);
	$r1 = db('nack1', fn($m) => false, ['table' => 't3', 'config' => $cfg]);
	$r2 = db('nack1', fn($m) => $m === 'nack-me', ['table' => 't3', 'config' => $cfg]);
	return $r1 === true && $r2 === true;
}, true);
test('消费-nack多次不丢失', function() use ($cfg){
	db('nack2', 'persist', ['table' => 't3', 'config' => $cfg]);
	$r1 = db('nack2', fn($m) => false, ['table' => 't3', 'config' => $cfg]);
	$r2 = db('nack2', fn($m) => false, ['table' => 't3', 'config' => $cfg]);
	$r3 = db('nack2', fn($m) => $m === 'persist', ['table' => 't3', 'config' => $cfg]);
	return $r1 && $r2 && $r3;
}, true);

// ========== 消费 discard ==========
test('消费-discard返回true', function() use ($cfg){
	db('disc1', 'x', ['table' => 't4', 'config' => $cfg]);
	return db('disc1', fn($m) => null, ['table' => 't4', 'config' => $cfg]);
}, true);
test('消费-discard后消息不存在', function() use ($cfg){
	return db('disc1', fn($m) => true, ['table' => 't4', 'config' => $cfg]);
}, false);

// ========== 消费 retry ==========
test('消费-retry延迟调度', function() use ($cfg){
	db('ret1', 'later', ['table' => 't5', 'config' => $cfg]);
	$r1 = db('ret1', fn($m) => 1, ['table' => 't5', 'config' => $cfg]);
	sleep(1);
	$r2 = db('ret1', fn($m) => $m === 'later', ['table' => 't5', 'config' => $cfg]);
	return $r1 === true && $r2 === true;
}, true);
test('消费-retry未到期不可消费', function() use ($cfg){
	db('ret2', 'wait', ['table' => 't5', 'config' => $cfg]);
	db('ret2', fn($m) => 3, ['table' => 't5', 'config' => $cfg]);
	return db('ret2', fn($m) => true, ['table' => 't5', 'config' => $cfg]);
}, false);

// ========== 空队列 ==========
test('空队列返回false', function() use ($cfg){
	return db('empty1', fn($m) => true, ['table' => 't6', 'config' => $cfg]);
}, false);

// ========== 复杂数据类型 ==========
test('复杂-嵌套数组', function() use ($cfg){
	$data = ['user' => ['name' => 'Alice', 'tags' => [1, 2]], 'meta' => ['k' => null]];
	db('cmp1', $data, ['table' => 't7', 'config' => $cfg]);
	$got = null;
	db('cmp1', function($m) use (&$got){ $got = $m; return true; }, ['table' => 't7', 'config' => $cfg]);
	return $got === $data;
}, true);
test('复杂-序列化边界-false', function() use ($cfg){
	db('cmp2', false, ['table' => 't7', 'config' => $cfg]);
	$got = null;
	db('cmp2', function($m) use (&$got){ $got = $m; return true; }, ['table' => 't7', 'config' => $cfg]);
	return $got === false;
}, true);
test('复杂-序列化边界-空数组', function() use ($cfg){
	db('cmp3', [], ['table' => 't7', 'config' => $cfg]);
	$got = null;
	db('cmp3', function($m) use (&$got){ $got = $m; return true; }, ['table' => 't7', 'config' => $cfg]);
	return $got === [];
}, true);
test('复杂-序列化边界-空字符串', function() use ($cfg){
	db('cmp4', '', ['table' => 't7', 'config' => $cfg]);
	$got = null;
	db('cmp4', function($m) use (&$got){ $got = $m; return true; }, ['table' => 't7', 'config' => $cfg]);
	return $got === '';
}, true);

// ========== 返回值边界 ==========
test('返回值-0等同discard', function() use ($cfg){
	db('edge1', 'val', ['table' => 't8', 'config' => $cfg]);
	db('edge1', fn($m) => 0, ['table' => 't8', 'config' => $cfg]);
	return db('edge1', fn($m) => true, ['table' => 't8', 'config' => $cfg]);
}, false);
test('返回值-负数等同discard', function() use ($cfg){
	db('edge2', 'val', ['table' => 't8', 'config' => $cfg]);
	db('edge2', fn($m) => -1, ['table' => 't8', 'config' => $cfg]);
	return db('edge2', fn($m) => true, ['table' => 't8', 'config' => $cfg]);
}, false);
test('返回值-字符串等同discard', function() use ($cfg){
	db('edge3', 'val', ['table' => 't8', 'config' => $cfg]);
	db('edge3', fn($m) => 'anything', ['table' => 't8', 'config' => $cfg]);
	return db('edge3', fn($m) => true, ['table' => 't8', 'config' => $cfg]);
}, false);

// ========== 多队列隔离 ==========
test('多队列-隔离', function() use ($cfg){
	db('qa', 'a-msg', ['table' => 't9a', 'config' => $cfg]);
	db('qb', 'b-msg', ['table' => 't9b', 'config' => $cfg]);
	$ga = null; $gb = null;
	db('qa', function($m) use (&$ga){ $ga = $m; return true; }, ['table' => 't9a', 'config' => $cfg]);
	db('qb', function($m) use (&$gb){ $gb = $m; return true; }, ['table' => 't9b', 'config' => $cfg]);
	return $ga === 'a-msg' && $gb === 'b-msg';
}, true);

// ========== 直接传 config ==========
test('直接传config参数', function() use ($cfg){
	return db('direct', 'val', ['table' => 't10', 'config' => $cfg]);
}, true);
test('直接传config消费', function() use ($cfg){
	return db('direct', fn($m) => $m === 'val', ['table' => 't10', 'config' => $cfg]);
}, true);

test();
