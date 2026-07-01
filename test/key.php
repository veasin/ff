<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, test, key};

container('#key', [
	'user'    => ['user:{id}', 'apcu' => 'user_cache:{id}', 'redis' => 'user:{id}'],
	'session' => ['sess:{token}'],
	'token'   => ['redis' => 'token:{uid}'],
	'article' => 'art:{id}',
]);

test('0号默认模板', fn() => key('user', ['id' => 123]), 'user:123');
test('指定层存在', fn() => key('user', ['id' => 1], 'apcu'), 'user_cache:1');
test('另一指定层存在', fn() => key('user', ['id' => 1], 'redis'), 'user:1');
test('指定层不存在回退0号', fn() => key('session', ['token' => 'abc'], 'redis'), 'sess:abc');
test('指定层不存在无0号返回key名', fn() => key('token', ['uid' => 5], 'apcu'), 'token');
test('无layer有0号', fn() => key('session'), 'sess:{token}');
test('无layer无0号返回key名', fn() => key('token'), 'token');
test('具体层', fn() => key('token', ['uid' => 5], 'redis'), 'token:5');
test('字符串简写', fn() => key('article'), 'art:{id}');
test('未配置key', fn() => key('un_config'), 'un_config');
test('未配置内联模板', fn() => key('xx:{id}', ['id' => 456]), 'xx:456');
test();

