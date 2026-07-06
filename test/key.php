<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, test, key};

container('^#kt', [
	'greet' => 'hello',
	'user'  => ['default_user', 'admin' => 'admin_user', 'vip' => 'vip_user'],
	'role'  => ['guest'],
	'fall'  => ['main', 'en' => 'english', 'zh' => 'chinese'],
	'count' => 42,
	'active'=> true,
]);

test('字符串直接返回', fn() => key('#kt.greet'), 'hello');
test('数组返回0号', fn() => key('#kt.role'), 'guest');
test('数组返回0号默认', fn() => key('#kt.user'), 'default_user');
test('指定层admin', fn() => key('#kt.user', 'admin'), 'admin_user');
test('指定层vip', fn() => key('#kt.user', 'vip'), 'vip_user');
test('指定层en', fn() => key('#kt.fall', 'en'), 'english');
test('指定层zh', fn() => key('#kt.fall', 'zh'), 'chinese');
test('层不存在回退0号', fn() => key('#kt.user', 'nonexist'), 'default_user');
test('路径不存在返回null', fn() => key('#kt.nonexist'), null);
test('路径不存在指定层返回null', fn() => key('#kt.nonexist', 'x'), null);
test('int 原样返回', fn() => key('#kt.count'), 42);
test('bool 原样返回', fn() => key('#kt.active'), true);

test();
