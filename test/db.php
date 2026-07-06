<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, db, test};

// ========== 基础功能测试 ==========
test('db函数存在', fn() => function_exists('ff\db'), true);
// ========== 配置管理测试 ==========
test('配置缺失-返回null', function(){
	// 使用一个不存在的配置名
	$result = db('SELECT 1', 'value', 'non_existent_config');
	return $result === null;
}, true);
test('默认配置不存在-返回null', function(){
	// 使用一个临时配置名，确保没有配置
	$result = db('SELECT 1', 'value', 'temp_config');
	return $result === null;
}, true);
// ========== 功能测试 ==========
test('db函数配置加载', function(){
	// 使用唯一配置名避免冲突
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	$result = db('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)', 'ok', $configName);
	return $result === true;
}, true);
test('ok模式-创建表返回true', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	$result = db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	return $result === true;
}, true);
test('id模式-INSERT返回ID', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	$id = db("INSERT INTO users (name) VALUES ('John')", 'id', $configName);
	return is_numeric($id) && $id > 0;
}, true);
test('count模式-UPDATE返回影响行数', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	$id = db("INSERT INTO users (name) VALUES ('Jane')", 'id', $configName);
	$affected = db("UPDATE users SET name = 'Jane2' WHERE id = ?", [$id], 'count', $configName);
	return $affected === 1;
}, true);
test('row模式-单行查询返回数组', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	$id = db("INSERT INTO users (name) VALUES ('Alice')", 'id', $configName);
	$user = db('SELECT * FROM users WHERE id = ?', [$id], 'row', $configName);
	return is_array($user) && $user['name'] === 'Alice';
}, true);
test('list模式-列表查询返回数组', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	db("INSERT INTO users (name) VALUES ('User1')", 'ok', $configName);
	db("INSERT INTO users (name) VALUES ('User2')", 'ok', $configName);
	$users = db('SELECT * FROM users', 'list', $configName);
	return is_array($users) && count($users) === 2;
}, true);
test('value模式-COUNT查询返回数字', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	db("INSERT INTO users (name) VALUES ('User1')", 'ok', $configName);
	db("INSERT INTO users (name) VALUES ('User2')", 'ok', $configName);
	$count = db('SELECT COUNT(*) FROM users', 'value', $configName);
	return is_numeric($count) && $count == 2;
}, true);
test('column模式-返回单列数组', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	db("INSERT INTO users (name) VALUES ('Name1')", 'ok', $configName);
	db("INSERT INTO users (name) VALUES ('Name2')", 'ok', $configName);
	$names = db('SELECT name FROM users', 'column', $configName);
	return is_array($names) && count($names) === 2 && $names[0] === 'Name1';
}, true);
test('错误SQL-返回null', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	$result = db('SELECT * FROM non_existent_table', 'list', $configName);
	return $result === null;
}, true);
// ========== 智能参数识别测试 ==========
test('智能参数-省略params作为mode', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	db("INSERT INTO users (name) VALUES ('Smart1')", 'ok', $configName);
	$users = db('SELECT * FROM users', 'list', $configName);
	return is_array($users) && count($users) === 1;
}, true);
test('智能参数-完整四个参数', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	$id = db("INSERT INTO users (name) VALUES ('FullParams')", [], 'id', $configName);
	$user = db('SELECT * FROM users WHERE id = ?', [$id], 'row', $configName);
	return is_array($user) && $user['name'] === 'FullParams';
}, true);
test('事务-BEGIN返回true', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	$result = db('BEGIN', [], null, $configName);
	return $result === true;
}, true);
test('事务-commit返回true', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	db('BEGIN', [], null, $configName);
	db("INSERT INTO users (name) VALUES ('TxTest')", 'ok', $configName);
	$result = db('COMMIT', [], null, $configName);
	return $result === true;
}, true);
test('事务-rollback回滚数据', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	db('BEGIN', [], null, $configName);
	db("INSERT INTO users (name) VALUES ('RollbackMe')", 'ok', $configName);
	db('ROLLBACK', [], null, $configName);
	$count = db('SELECT COUNT(*) FROM users', 'value', $configName);
	return $count == 0;
}, true);
test('exec模式-多DDL执行返回true', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	$r = db("CREATE TABLE a (id INT); CREATE TABLE b (id INT);", 'exec', $configName);
	return $r === true;
}, true);
test('exec模式-混合DDL+DML返回影响行数', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db("CREATE TABLE t (id INT)", 'exec', $configName);
	$r = db("INSERT INTO t (id) VALUES (1); INSERT INTO t (id) VALUES (2);", 'exec', $configName);
	return is_int($r) && $r === 1;
}, true);
test('exec模式-错误SQL返回null', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	$r = db("CREATE TABLE", 'exec', $configName);
	return $r === null;
}, true);
test('exec模式-跳位语法支持', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	$r = db("CREATE TABLE c (id INT); CREATE TABLE d (id INT);", 'exec', $configName);
	return $r === true;
}, true);
test('事务-小写sql支持', function(){
	$configName = 'test_' . uniqid();
	container("#db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	db('begin', [], null, $configName);
	db("INSERT INTO users (name) VALUES ('Lower')", 'ok', $configName);
	$result = db('commit', [], null, $configName);
	return $result === true;
}, true);
test();