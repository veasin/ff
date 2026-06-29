<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, name, test};

// 设置配置
container('#name', [
	'redis' => [
		'uid' => 'user:{id}',
		'session' => 'sess:{token}',
	],
	'db' => [
		'users' => 'tbl_users',
	],
]);
// 测试用例
test('基本key获取', fn() => name('uid', ['id' => 123], 'redis'), 'user:123');
test('模板替换', fn() => name('session', ['token' => 'abc123'], 'redis'), 'sess:abc123');
test('无命名空间key', fn() => name('users', null, 'db'), 'tbl_users');
test('未配置的key', fn() => name('un_config'), 'un_config');
test('无占位符模板', fn() => name('xx:{id}', ['id' => 456]), 'xx:456');
test();