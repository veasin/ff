<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, i18n, test};

container(null);
// ---- request 级增量覆盖 ----
container('i18n.#ff.error.internal', '自定义错误');
test('request 覆盖', i18n('#ff.error.internal'), '自定义错误');
container(null);
// ---- 用户自定义 key ----
container('i18n.myapp.welcome', ['你好 {name}', 'en_US' => 'Hello {name}']);
test('用户翻译 + {name}', i18n('myapp.welcome', ['name' => '张三']), '你好 张三');
test('用户强制 en_US', i18n('myapp.welcome', ['name' => 'Alice'], 'en_US'), 'Hello Alice');
container(null);
// ---- 不存在返回 key ----
test('不存在返回 key', i18n('not_exists'), 'not_exists');
test('不存在带占位符回退', i18n('{message}', ['message' => 'error msg']), 'error msg');
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
test() |> (fn($a)=>json_encode($a, JSON_UNESCAPED_UNICODE |JSON_UNESCAPED_SLASHES)) |> printf(...);
