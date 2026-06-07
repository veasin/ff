<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{container, i18n, test};

container(null);
// ---- core 默认翻译 ----
test('core 默认 #error:internal', i18n('#error:internal'), '服务器内部错误');
test('core 强制 en_US', i18n('#error:internal', null, 'en_US'), 'Internal server error');
test('core 强制语言 2nd string', i18n('#error:internal', 'en_US'), 'Internal server error');
test('core #auth:realm_basic', i18n('#auth:realm_basic'), '需要认证');
test('core #container:key_empty', i18n('#container:key_empty'), '键名不能为空');
// ---- request 级增量覆盖 ----
container('i18n.zh_CN.#error:internal', '自定义错误');
test('request 覆盖', i18n('#error:internal'), '自定义错误');
container(null); // 清空
// ---- 用户自定义 key ----
container('i18n.zh_CN.welcome', '你好 {name}');
container('i18n.en_US.welcome', 'Hello {name}');
test('用户翻译 + {name}', i18n('welcome', ['name' => '张三']), '你好 张三');
test('用户强制 en_US', i18n('welcome', ['name' => 'Alice'], 'en_US'), 'Hello Alice');
test('用户 强制语言 2nd string', i18n('#error:internal', 'en_US'), 'Internal server error');
container(null);
// ---- . 自动转 _ ----
container('i18n.zh_CN.dot_key', '带点的键');
test('. 转 _', i18n('dot.key'), '带点的键');
container(null);
// ---- 不存在返回 key ----
test('不存在返回 key', i18n('not_exists'), 'not_exists');
test('不存在 . 返回 _', i18n('not.exists'), 'not_exists');
// ---- 语言设置 ----
test('lang: 设置 en_US', function(){
	i18n(lang: 'en_US');
	return container('i18n.lang');
}, 'en_US');
test('lang: 重置 zh_CN', function(){
	i18n(lang: 'zh_CN');
	return container('i18n.lang');
}, 'zh_CN');
container(null);
test();
