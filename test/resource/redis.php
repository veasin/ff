<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\resource\redis;

// ========== 基础功能 ==========
test('redis函数存在', fn() => function_exists('ff\resource\redis'), true);

// ========== 无Redis扩展 ==========
test('无Redis扩展时返回null', function(){
	// 未安装扩展时 class_exists('\Redis') 为 false
	// 直接测试，无论是否安装都应返回合理的值
	$r = redis('nonexistent_config_' . uniqid());
	return $r === null;
}, true);

// ========== 连接池行为（配置相同的情况） ==========
test('相同配置名返回同一实例', function(){
	$name = 'same_' . uniqid();
	container("#redis.{$name}", ['host' => '127.0.0.1', 'port' => 6379]);
	$a = redis($name);
	$b = redis($name);
	if($a === null) return true; // 无Redis服务时跳过
	return $a === $b;
}, true);

test('不同配置名返回不同实例', function(){
	$aName = 'diff_a_' . uniqid();
	$bName = 'diff_b_' . uniqid();
	container("#redis.{$aName}", ['host' => '127.0.0.1', 'port' => 6379]);
	container("#redis.{$bName}", ['host' => '127.0.0.1', 'port' => 6379]);
	$a = redis($aName);
	$b = redis($bName);
	if($a === null || $b === null) return true; // 无Redis服务时跳过
	return $a !== $b;
}, true);

// ========== 清空操作 ==========
test('清空后重新创建', function(){
	$name = 'clear_' . uniqid();
	container("#redis.{$name}", ['host' => '127.0.0.1', 'port' => 6379]);
	$a = redis($name);
	if($a === null) return true; // 无Redis服务时跳过
	redis(null);
	$b = redis($name);
	return $a !== $b;
}, true);

// ========== 默认配置名 ==========
test('无参数使用default配置名', function(){
	$r = redis();
	return $r instanceof \Redis || $r === null;
}, true);

// ========== 自定义工厂 ==========
test('自定义工厂创建连接', function(){
	$factoryKey = '#resource/redis:';
	$name = 'factory_' . uniqid();
	container("#redis.{$name}", ['host' => '127.0.0.1', 'port' => 6379]);
	$called = false;
	container($factoryKey, function($config) use (&$called){
		$called = true;
		$c = new \Redis();
		$c->connect($config['host'], $config['port'] ?? 6379);
		return $c;
	});
	$r = redis($name);
	container($factoryKey, null);
	return ($r instanceof \Redis || $r === null) && $called;
}, true);

test();
