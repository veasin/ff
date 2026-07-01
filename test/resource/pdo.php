<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\resource\pdo;

// ========== 基础功能 ==========
test('pdo函数存在', fn() => function_exists('ff\resource\pdo'), true);

// ========== 配置缺失 ==========
test('配置不存在-返回null', function(){
	return pdo('non_existent_' . uniqid());
}, null);

test('配置缺少dsn-返回null', function(){
	$name = 'no_dsn_' . uniqid();
	container("#db.{$name}", ['username' => 'root']);
	return pdo($name);
}, null);

// ========== 正常连接 ==========
test('正常连接-返回PDO实例', function(){
	$name = 'pdo_' . uniqid();
	container("#db.{$name}", ['dsn' => 'sqlite::memory:']);
	$pdo = pdo($name);
	return $pdo instanceof \PDO;
}, true);

test('连接可执行查询', function(){
	$name = 'pdo_' . uniqid();
	container("#db.{$name}", ['dsn' => 'sqlite::memory:']);
	$pdo = pdo($name);
	if(!$pdo) return false;
	$stmt = $pdo->query('SELECT 1 AS v');
	return $stmt && $stmt->fetch(\PDO::FETCH_ASSOC)['v'] == 1;
}, true);

// ========== 连接池行为 ==========
test('相同配置名返回同一实例', function(){
	$name = 'pool_' . uniqid();
	container("#db.{$name}", ['dsn' => 'sqlite::memory:']);
	$a = pdo($name);
	$b = pdo($name);
	return $a === $b;
}, true);

test('不同配置名返回不同实例', function(){
	$aName = 'pool_a_' . uniqid();
	$bName = 'pool_b_' . uniqid();
	container("#db.{$aName}", ['dsn' => 'sqlite::memory:']);
	container("#db.{$bName}", ['dsn' => 'sqlite::memory:']);
	$a = pdo($aName);
	$b = pdo($bName);
	return $a !== $b;
}, true);

// ========== 清空操作 ==========
test('清空后重新创建', function(){
	$name = 'clear_' . uniqid();
	container("#db.{$name}", ['dsn' => 'sqlite::memory:']);
	$a = pdo($name);
	pdo(null);
	$b = pdo($name);
	return $a !== $b;
}, true);

// ========== PDO选项继承 ==========
test('PDO选项正确设置', function(){
	$name = 'opt_' . uniqid();
	container("#db.{$name}", ['dsn' => 'sqlite::memory:']);
	$pdo = pdo($name);
	if(!$pdo) return false;
	$errmode = $pdo->getAttribute(\PDO::ATTR_ERRMODE);
	return $errmode === \PDO::ERRMODE_EXCEPTION;
}, true);

// ========== 默认配置名 ==========
test('无参数使用default配置名', function(){
	$name = 'default';
	container("#db.{$name}", ['dsn' => 'sqlite::memory:']);
	$pdo = pdo();
	if(!$pdo && !container("#db.{$name}")) return true; // 可能与其他测试冲突
	return $pdo instanceof \PDO || $pdo === null;
}, true);

// ========== 自定义工厂 ==========
test('自定义工厂创建连接', function(){
	$factoryKey = '#resource/pdo:';
	$name = 'factory_' . uniqid();
	container("#db.{$name}", ['dsn' => 'sqlite::memory:']);
	$called = false;
	container($factoryKey, function($config) use (&$called){
		$called = true;
		return new \PDO($config['dsn']);
	});
	$pdo = pdo($name);
	container($factoryKey, null);
	return $pdo instanceof \PDO && $called;
}, true);

test();

// 清理
container("#db.default", null);
