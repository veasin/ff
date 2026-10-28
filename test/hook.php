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

// ========== 优先级测试开始 ==========

// 清理之前的钩子
container('^#hook.[]');
container('#hook.priority');
container('#hook.after_priority');

$priorityOrder = [];
$collectPriority = function(string $name) use (&$priorityOrder) :callable{
	return function() use ($name, &$priorityOrder){ $priorityOrder[] = $name; };
};

// 测试 10: 注册时直接插入，不排序（验证注册行为）
hook('after_priority', $collectPriority('p10'));
$stored = container('#hook.after_priority');
test('注册时直接插入，存储格式为 [回调，优先级]', is_callable($stored[0][0]) && ($stored[0][1] === 0), true);

// 测试 11: 不同优先级的回调触发顺序（数字越小越先执行）
hook('priority_test', $collectPriority('first'), -100);   // 高优先级
hook('priority_test', $collectPriority('middle'), 0);    // 默认优先级
hook('priority_test', $collectPriority('last'), 1000);   // Bootstrap，低优先级

$priorityOrder = [];
hook('priority_test');
test('触发时按优先级排序执行', $priorityOrder, ['first', 'middle', 'last']);

// 测试 12: Bootstrap 回调默认优先级为 1000（在序列后面）
hook('bootstrap_priority', $collectPriority('bootstrap'), 1000);
$stored = container('#hook.bootstrap_priority');
test('Bootstrap 回调存储优先级为 1000', is_callable($stored[0][0]) && ($stored[0][1] === 1000), true);

// 测试 13: 负优先级插入到最前面
hook('negative_priority', $collectPriority('zero'), 0);
hook('negative_priority', $collectPriority('minus50'), -50);
$priorityOrder = [];
hook('negative_priority');
test('负优先级在默认优先级之前执行', $priorityOrder, ['minus50', 'zero']);

// 测试 14: 多个负优先级的排序
hook('multi_negative', $collectPriority('minus100'), -100);
hook('multi_negative', $collectPriority('minus200'), -200);
hook('multi_negative', $collectPriority('minus50'), -50);
$priorityOrder = [];
hook('multi_negative');
test('多个负优先级按数字大小排序（越小越先）', $priorityOrder, ['minus200', 'minus100', 'minus50']);

// 测试 15: Bootstrap 回调在序列最后执行
hook('bootstrap_last', $collectPriority('normal'), 0);
hook('bootstrap_last', $collectPriority('bs'), 1000);
$priorityOrder = [];
hook('bootstrap_last');
test('Bootstrap 回调 (1000) 在最后执行', $priorityOrder, ['normal', 'bs']);

// 测试 16: Bootstrap 回调与负优先级组合
hook('mixed_priority', $collectPriority('minus10'), -10);
hook('mixed_priority', $collectPriority('normal'), 0);
hook('mixed_priority', $collectPriority('bs'), 1000);
$priorityOrder = [];
hook('mixed_priority');
test('混合优先级排序：负 < 默认 < Bootstrap(1000)', $priorityOrder, ['minus10', 'normal', 'bs']);

// ========== 优先级测试结束 ==========

test() |> (fn($a)=>json_encode($a, JSON_UNESCAPED_UNICODE |JSON_UNESCAPED_SLASHES)) |> printf(...);
