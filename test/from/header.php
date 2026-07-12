<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\from\header;

container('#in.headers', ['content-type' => 'application/json', 'x-custom' => 'val']);
test('header - 容器有值直接返回', header(), ['content-type' => 'application/json', 'x-custom' => 'val']);
test('header - 键名小写', header()['content-type'], 'application/json');

container(null);
container('#in.headers', null);
test('header - 容器为空时解析', header(), fn($v) => is_array($v));

container(null);
$_SERVER['HTTP_X_TOKEN'] = 'abc123';
test('header - 从SERVER解析', header()['x-token'], 'abc123');
unset($_SERVER['HTTP_X_TOKEN']);

container(null);
$_SERVER['HTTP_X_MULTI'] = 'a';
test('header - 单值头', header()['x-multi'], 'a');
unset($_SERVER['HTTP_X_MULTI']);

container(null);
container('#in.headers', ['accept' => ['text/html', 'application/json']]);
test('header - 多值头数组', header()['accept'], ['text/html', 'application/json']);

container(null);
test('header - 无HTTP_前缀时返回空', header(), []);

test();
