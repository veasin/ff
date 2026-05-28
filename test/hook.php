<?php
include __DIR__ . "/_boot.php";

use function nx\{container, hook, test};

$called = [];
$collect = function(string $name) use (&$called): callable{
	return function() use ($name, &$called){ $called[] = $name; };
};

// 测试 1: hook(true) 设置持久级默认序列
hook(true);
test('hook(true) 持久级默认序列', container('^#hook'), ['after', 'end']);

// 测试 2: 注册单个回调并触发
hook('after', $collect('after1'));
hook('after');
test('触发 after 单个回调', $called, ['after1']);

// 测试 3: 同一个钩子注册多个回调
hook('end', $collect('end1'));
hook('end', $collect('end2'));
$called = [];
hook('end');
test('end 多个回调按序执行', $called, ['end1', 'end2']);

// 测试 4: 触发后不清空，再次触发仍执行
$called = [];
hook('after');
test('触发后不清空', $called, ['after1']);

// 测试 5: 无参 hook() 触发默认序列
$called = [];
hook('after', $collect('after2'));
hook();
test('hook() 触发默认序列 [after, end]', $called, ['after1', 'after2', 'end1', 'end2']);

// 测试 6: 自定义序列
$called = [];
hook(['end', 'after']);
test('自定义序列 [end, after]', $called, ['end1', 'end2', 'after1', 'after2']);

// 测试 7: 覆盖持久级默认序列
hook(true, ['after']);
$called = [];
hook();
test('覆盖默认序列为 [after]', $called, ['after1', 'after2']);

// 测试 8: 恢复默认序列，测试不 hook(true) 也能注册触发
hook(true, ['after', 'end']);
$called = [];
hook('independent', $collect('indep'));
hook('independent');
test('不 hook(true) 也能注册触发', $called, ['indep']);

// 测试 9: 不存在的钩子触发不报错
$called = [];
hook('non_existent');
test('不存在的钩子触发不报错', $called, []);

// 测试 10: 持久级不受请求级 container(null) 影响
hook('persist_test', $collect('persist_hook'));
container(null);
hook('persist_test');
test('持久级 ^#hook 不受 container(null) 影响', container('^#hook'), ['after', 'end']);
test('请求级钩子在 container(null) 后被清空', container('#hook.persist_test'), null);

test();
