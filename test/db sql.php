<?php
include __DIR__ . "/../vendor/autoload.php";

use ff\helpers\sql;
use function ff\{container, db, test};

test('sql对象-类存在检查', fn() => class_exists('ff\helpers\sql'), true);
test('sql对象-插入并查询', function(){
	$configName = 'test_' . uniqid();
	container("db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)', 'ok', $configName);
	$query = sql::table('users')->insert(['name' => 'Test', 'email' => 'test@test.com']);
	$id = db($query, 'id', $configName);
	$user = db(sql::table('users')->where(['id' => $id])->select(), 'row', $configName);
	return $user['name'] === 'Test' && $user['email'] === 'test@test.com';
}, true);
test('sql对象-where条件查询', function(){
	$configName = 'test_' . uniqid();
	container("db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status INTEGER)', 'ok', $configName);
	db("INSERT INTO users (name, status) VALUES ('Active', 1)", 'ok', $configName);
	db("INSERT INTO users (name, status) VALUES ('Inactive', 0)", 'ok', $configName);
	$users = db(sql::table('users')->where(['status' => 1])->select(),
		'list',
		$configName
	);
	return count($users) === 1 && $users[0]['name'] === 'Active';
}, true);
test('sql对象-update操作', function(){
	$configName = 'test_' . uniqid();
	container("db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	$id = db("INSERT INTO users (name) VALUES ('Old')", 'id', $configName);
	$affected = db(sql::table('users')->where(['id' => $id])->update(['name' => 'New']),
		'count',
		$configName
	);
	$user = db("SELECT name FROM users WHERE id = ?", [$id], 'value', $configName);
	return $affected === 1 && $user === 'New';
}, true);
test('sql对象-delete操作', function(){
	$configName = 'test_' . uniqid();
	container("db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $configName);
	$id = db("INSERT INTO users (name) VALUES ('ToDelete')", 'id', $configName);
	$affected = db(sql::table('users')->where(['id' => $id])->delete(),
		'count',
		$configName
	);
	$count = db("SELECT COUNT(*) FROM users", 'value', $configName);
	return $affected === 1 && $count === 0;
}, true);
test('sql对象-select指定字段', function(){
	$configName = 'test_' . uniqid();
	container("db.{$configName}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)', 'ok', $configName);
	db("INSERT INTO users (name, email) VALUES ('User', 'user@test.com')", 'ok', $configName);
	$user = db(sql::table('users')->select(['id', 'name']),
		'row',
		$configName
	);
	return array_keys($user) === ['id', 'name'];
}, true);
test();
