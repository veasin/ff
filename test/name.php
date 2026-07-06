<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, test, name};

container('^#key', [
	'user'    => ['user:{id}', 'apcu' => 'user_cache:{id}', 'redis' => 'user:{id}'],
	'session' => ['sess:{token}'],
	'token'   => ['redis' => 'token:{uid}'],
	'article' => 'art:{id}',
	'retry'   => 3,
	'enabled' => true,
]);

// prefix 风格 —— 推荐
test('prefix 0号默认', fn() => name('user', ['id' => 123], prefix: '#key.'), 'user:123');
test('prefix 指定层', fn() => name('user', ['id' => 1], 'apcu', '#key.'), 'user_cache:1');
test('prefix 另一层', fn() => name('user', ['id' => 1], 'redis', '#key.'), 'user:1');
test('prefix 层不存在回退0号', fn() => name('session', ['token' => 'abc'], 'redis', '#key.'), 'sess:abc');
test('prefix 无layer有0号', fn() => name('session', prefix: '#key.'), 'sess:{token}');
test('prefix 具体层', fn() => name('token', ['uid' => 5], 'redis', '#key.'), 'token:5');
test('prefix 字符串简写', fn() => name('article', prefix: '#key.'), 'art:{id}');
test('prefix 层不存在回退name', fn() => name('token', ['uid' => 5], 'apcu', '#key.'), 'token');
test('prefix 未配置返回name', fn() => name('un_config', prefix: '#key.'), 'un_config');
test('prefix 未配置内联模板', fn() => name('xx:{id}', ['id' => 456], prefix: '#key.'), 'xx:456');
test('prefix 2参字符串简写layer', fn() => name('user', ['id' => 1], 'apcu', '#key.'), 'user_cache:1');
// 无 prefix —— 直接路径风格
test('直接路径', fn() => name('#key.session'), 'sess:{token}');
test('直接路径未配置', fn() => name('#key.un_config'), '#key.un_config');
test('prefix int 自动转string', fn() => name('retry', prefix: '#key.'), '3');
test('prefix int + 上下文替换', fn() => name('retry', ['id' => 999], prefix: '#key.'), '3');
test('prefix bool 自动转string', fn() => name('enabled', prefix: '#key.'), '1');
test('直接路径 int', fn() => name('#key.retry'), '3');
test('直接路径 bool', fn() => name('#key.enabled'), '1');
test();

