<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, test};

// 清理初始状态
container(null);
container(null, true);
// 测试 1: 基本设置和获取
container('test.key', 'value');
test('基本设置获取', container('test.key'), 'value');
// 测试 2: 简单key设置和获取
container('simple_key', 'simple_value');
test('简单key设置获取', container('simple_key'), 'simple_value');
// 测试 3: 嵌套数组访问
container('nested.deep.value', 'deep_value');
test('嵌套访问', container('nested.deep.value'), 'deep_value');
// 测试 4: 删除操作
container('test.key', null);
test('删除操作', container('test.key'), null);
// 测试 5: 清空请求级（持久 #ext 不受影响）
container('temp.key', 'temp_val');
container(null);
test('清空请求级', container('temp.key'), null);
// 测试 6: 存在性检查
container('check.key', 'exists');
test('存在性检查', container('check.key'), 'exists');
// 测试 7: 数组批量获取
container('batch.1', 'value1');
container('batch.2', 'value2');
test('批量获取', container(['batch.1', 'batch.2']), ['value1', 'value2']);
// 测试 8: 数组批量设置
container(['batch.set1' => 'set1', 'batch.set2' => 'set2']);
test('批量设置', container(['batch.set1', 'batch.set2']), ['set1', 'set2']);
// 测试 9: 延迟构建
container('lazy', fn() => 'lazy_value');
test('延迟构建', container('lazy*'), 'lazy_value');
// 测试 10: 简单key删除
container('simple_delete', 'delete_me');
container('simple_delete', null);
test('简单key删除', container('simple_delete'), null);
// 测试 11: 简单key不存在检查
test('简单key不存在检查', container('nonexistent'), null);
// 测试 12: 简单key批量获取
container('batch_simple1', 'value1');
container('batch_simple2', 'value2');
test('简单key批量获取', container(['batch_simple1', 'batch_simple2']), ['value1', 'value2']);
// 测试 13: 简单key批量设置
container(['simple_batch_set1' => 'set1', 'simple_batch_set2' => 'set2']);
test('简单key批量设置', container(['simple_batch_set1', 'simple_batch_set2']), ['set1', 'set2']);
// 测试 14: ^ 前缀写入持久
container('^persist.key', 'persist_value');
test('^前缀持久写入', container('^persist.key'), 'persist_value');
// 测试 15: 请求覆盖持久（标量）
container('persist.key', 'request_value');
test('请求覆盖持久', container('persist.key'), 'request_value');
test('仍可读持久', container('^persist.key'), 'persist_value');
// 测试 16: * 后缀执行闭包
container('factory', fn() => 'executed');
test('*后缀执行闭包', container('factory*'), 'executed');
test('无后缀返回闭包自身', container('factory') instanceof Closure, true);
// 测试 17: 非闭包忽略 * 后缀
container('plain', 'string_val');
test('非闭包忽略*', container('plain*'), 'string_val');
// 测试 18: container(null, true) 清理持久
container(null);
test('清理请求后持久不变', container('^persist.key'), 'persist_value');
container(null, true);
test('清理持久', container('^persist.key'), null);
// 测试 19: container(array, true) 批量持久写入
container(['^batch_persist.a' => 'a', '^batch_persist.b' => 'b'], true);
test('批量持久写入', container(['^batch_persist.a', '^batch_persist.b']), ['a', 'b']);
// 测试 20: ^ 和 * 组合使用
container('^combo', fn() => 'combo_result');
test('^和*组合', container('^combo*'), 'combo_result');
// 测试 21: 嵌套key持久
container('^nested.deep.persist', 'deep_value');
test('嵌套持久', container('^nested.deep.persist'), 'deep_value');
// 测试 22: container(null, '^') 也清理持久
container('^persist.test', 'val');
container(null, '^');
test('container(null, "^")清理持久', container('^persist.test'), null);
// 测试 23: container(null, null) 无操作
container('^persist.test2', 'val2');
container(null, null);
test('container(null, null) 不影响持久', container('^persist.test2'), 'val2');
// 测试 24: * 写操作触发 warning
container(null);
container(null, true);
$warned = false;
set_error_handler(function($errno) use (&$warned){ $warned = $errno === E_USER_WARNING; });
container('write_star*', 'val');
restore_error_handler();
test('*写操作触发warning', $warned, true);
test('*写操作仍正常存储', container('write_star'), 'val');
// ========== 深度合并测试 ==========
// 清理状态
container(null);
container(null, true);
// 测试 25: 废除0参
test('0参返回null', container(), null);
// 测试 26: 关联数组深度合并
container('^merge.assoc', ['a' => 1, 'b' => ['c' => 2]]);
container('merge.assoc', ['a' => 2, 'b' => ['d' => 3]]);
test('关联数组深度合并', container('merge.assoc'), ['a' => 2, 'b' => ['c' => 2, 'd' => 3]]);
// 测试 27: 标量请求覆盖持久
container('^merge.scalar', 'old');
container('merge.scalar', 'new');
test('标量请求覆盖持久', container('merge.scalar'), 'new');
// 测试 28: 请求级不存在时回退持久
container('^merge.fallback', ['x' => 1]);
test('请求无key回退持久', container('merge.fallback'), ['x' => 1]);
// 测试 29: 持久级不存在时请求级生效
container('merge.OnlyReq', ['y' => 2]);
test('仅请求级存在', container('merge.OnlyReq'), ['y' => 2]);
// 测试 30: 数字索引数组直接替换（不合并）
container('^merge.list', [1, 2, 3]);
container('merge.list', [4, 5]);
test('数字索引数组替换', container('merge.list'), [4, 5]);
// 测试 31: 请求标量覆盖持久数组
container('^merge.overwrite', ['a' => 1]);
container('merge.overwrite', 'string');
test('请求标量覆盖持久数组', container('merge.overwrite'), 'string');
// 测试 32: 持久标量+请求数组 → 请求生效
container('^merge.overwrite2', 'string');
container('merge.overwrite2', ['a' => 1]);
test('请求数组覆盖持久标量', container('merge.overwrite2'), ['a' => 1]);
// 测试 33: 多层嵌套深度合并
container('^merge.deep', ['l1' => ['l2' => ['l3a' => 'old', 'l3b' => 'keep']]]);
container('merge.deep', ['l1' => ['l2' => ['l3a' => 'new']]]);
test('多层嵌套深度合并', container('merge.deep'), ['l1' => ['l2' => ['l3a' => 'new', 'l3b' => 'keep']]]);
// 测试 34: 内层含数字索引数组不合并
container('^merge.innerList', ['a' => [1, 2], 'b' => ['c' => 1]]);
container('merge.innerList', ['a' => [3], 'b' => ['d' => 2]]);
test('内层含列表替换', container('merge.innerList'), ['a' => [3], 'b' => ['c' => 1, 'd' => 2]]);
// 测试 35: ^ 前缀读取不合并（仅读持久级）
container('^merge.persistOnly', ['a' => 1]);
container('merge.persistOnly', ['b' => 2]);
test('^前缀不合并', container('^merge.persistOnly'), ['a' => 1]);
// 测试 36: 清理请求后仅持久生效
container(null);
container('^merge.afterClean', ['x' => 1]);
container('merge.afterClean', ['y' => 2]);
container(null);
test('清理请求后仅持久', container('merge.afterClean'), ['x' => 1]);
// 测试 37: 模拟 #input.type bootstrap 场景
container(null);
container(null, true);
container('^#input.type', [
	'int' => fn($v) => (int)$v,
	'str' => fn($v) => (string)$v,
]);
container('#input.type', [
	'float' => fn($v) => (float)$v,
]);
$merged = container('#input.type');
test('bootstrap场景-int保留', isset($merged['int']), true);
test('bootstrap场景-str保留', isset($merged['str']), true);
test('bootstrap场景-float添加', isset($merged['float']), true);
// 测试 38: 请求级覆盖 bootstrap 规则
container('#input.type', [
	'int' => fn($v) => (int)$v * 2,
]);
$merged2 = container('#input.type');
test('bootstrap场景-int被覆盖', $merged2['int'] !== null, true);
// 测试 39: 嵌套key单写不影响父级合并
container(null);
container(null, true);
container('^merge.singleWrite', ['a' => ['b' => 1], 'c' => 2]);
container('merge.singleWrite.a.d', 3);
$singleResult = container('merge.singleWrite');
test('嵌套key单写-父级合并', $singleResult['c'], 2);
test('嵌套key单写-子级合并', $singleResult['a']['b'], 1);
test('嵌套key单写-新值', $singleResult['a']['d'], 3);
test();
// 最终清理
container(null);
container(null, true);
