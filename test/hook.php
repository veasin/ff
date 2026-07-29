<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, hook, test};

$called = [];
$collect = function(string $name) use (&$called): callable{
	return function() use ($name, &$called){ $called[] = $name; };
};

// bootstrap 已设 ^#hook.[] = ['after', 'end']，验证持久级默认序列
test('持久级默认序列', container('^#hook.[]'), ['after', 'end']);

// 测试 1: 注册单个回调并触发
hook('after', $collect('after1'));
hook('after');
test('触发 after 单个回调', $called, ['after1']);

// 测试 2: 同一个钩子注册多个回调
hook('end', $collect('end1'));
hook('end', $collect('end2'));
$called = [];
hook('end');
test('end 多个回调按序执行', $called, ['end1', 'end2']);

// 测试 3: 触发后不清空，再次触发仍执行
$called = [];
hook('after');
test('触发后不清空', $called, ['after1']);

// 测试 4: 无参 hook() 触发默认序列
$called = [];
hook('after', $collect('after2'));
hook();
test('hook() 触发默认序列 [after, end]', $called, ['after1', 'after2', 'end1', 'end2']);

// 测试 5: 自定义序列
$called = [];
hook(['end', 'after']);
test('自定义序列 [end, after]', $called, ['end1', 'end2', 'after1', 'after2']);

// 测试 6: 覆盖持久级默认序列
container('^#hook.[]', ['after']);
$called = [];
hook();
test('覆盖默认序列为 [after]', $called, ['after1', 'after2']);

// 测试 7: 恢复持久级默认序列
container('^#hook.[]', ['after', 'end']);
$called = [];
hook('independent', $collect('indep'));
hook('independent');
test('不依赖持久级序列也能注册触发', $called, ['indep']);

// 测试 8: 不存在的钩子触发不报错
$called = [];
hook('non_existent');
test('不存在的钩子触发不报错', $called, []);

// 测试 9: 持久级不受请求级 container(null) 影响
hook('persist_test', $collect('persist_hook'));
container(null);
hook('persist_test');
test('持久级 ^#hook.[] 不受 container(null) 影响', container('^#hook.[]'), ['after', 'end']);
test('请求级钩子在 container(null) 后被清空', container('#hook.persist_test'), null);

test();
