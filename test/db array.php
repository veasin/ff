<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, db, test};

test('db数组-SELECT list', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('Alice')", 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('Bob')", 'ok', $cn);
	$users = db(['SELECT', 'from' => 'users'], 'list', $cn);
	return is_array($users) && count($users) === 2;
}, true);

test('db数组-SELECT row', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('Alice')", 'ok', $cn);
	$user = db(['SELECT', 'fields' => ['id', 'name'], 'from' => 'users', 'where' => ['name' => 'Alice']], 'row', $cn);
	return is_array($user) && $user['name'] === 'Alice';
}, true);

test('db数组-SELECT value', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('Alice')", 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('Bob')", 'ok', $cn);
	$count = db(['SELECT', 'fields' => [['COUNT', '*']], 'from' => 'users'], 'value', $cn);
	return is_numeric($count) && $count == 2;
}, true);

test('db数组-INSERT id', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	$id = db(['INSERT', 'table' => 'users', 'value' => ['name' => 'NewUser']], 'id', $cn);
	return is_numeric($id) && $id > 0;
}, true);

test('db数组-UPDATE count', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	$id = db("INSERT INTO users (name) VALUES ('OldName')", 'id', $cn);
	$affected = db(['UPDATE', 'from' => 'users', 'set' => ['name' => 'NewName'], 'where' => ['id' => $id]], 'count', $cn);
	return $affected === 1;
}, true);

test('db数组-DELETE count', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	$id = db("INSERT INTO users (name) VALUES ('ToDelete')", 'id', $cn);
	$affected = db(['DELETE', 'from' => 'users', 'where' => ['id' => $id]], 'count', $cn);
	return $affected === 1;
}, true);

test('db数组-省略params直接传mode', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('Direct')", 'ok', $cn);
	$users = db(['SELECT', 'from' => 'users'], 'list', $cn);
	return is_array($users) && count($users) === 1;
}, true);

test('db数组-指定fields和order', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('B'), ('A'), ('C')", 'ok', $cn);
	$names = db(['SELECT', 'fields' => ['name'], 'from' => 'users', 'order' => ['name' => 'ASC']], 'column', $cn);
	return $names === ['A', 'B', 'C'];
}, true);

test('db数组-limit offset', function(){
	$cn = 'test_' . uniqid();
	container("#db.{$cn}", ['dsn' => 'sqlite::memory:']);
	db('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)', 'ok', $cn);
	db("INSERT INTO users (name) VALUES ('A'), ('B'), ('C'), ('D')", 'ok', $cn);
	$users = db(['SELECT', 'from' => 'users', 'limit' => 2, 'offset' => 1], 'list', $cn);
	return is_array($users) && count($users) === 2;
}, true);

test();

