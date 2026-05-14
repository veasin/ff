<?php
require __DIR__ . '/../vendor/autoload.php';

use function nx\{container, input, test};

container("#in.input", [
	'method' => 'get',
	'uri' => '/users/123?name=test',
	'params' => null,
]);
container('#in.params', ['id' => '123', 'name' => 'test']);
$_GET = ['name' => 'test'];
test('input source - 完整数组', input(null, 'input'), fn($v) => is_array($v) && isset($v['method']) && isset($v['uri']));
test('input source - method', input('method', 'input'), 'get');
test('input source - uri', input('uri', 'input'), '/users/123?name=test');
test('input source - params (web)', input('params', 'input'), null);
test('params source - 容器有值', input('id', 'params'), '123');
test('params source - 容器无值', input('name', 'params'), 'test');
container("#in.input", [
	'method' => 'cli',
	'uri' => 'cli.php --name=test --id=123',
	'params' => ['name' => 'test', 'id' => '123'],
]);
test('input source - cli mode', input('method', 'input'), 'cli');
test('input source - cli uri', input('uri', 'input'), 'cli.php --name=test --id=123');
test('input source - cli params', input('params', 'input'), fn($v) => is_array($v) && isset($v['name']));
test('query source', input('name', 'query'), 'test');
test('name=null 返回数组', input(null, 'query'), ['name' => 'test']);
$_POST = ['id' => '123', 'name' => 'test'];
container("#in.body", $_POST);
test('body source', input('id', 'body'), '123');
test('input + filter - int转换', input('id', 'body', 'int'), 123);
test('input + filter - int + 范围验证', input('id', 'body', 'int', '>100'), 123);
test('input + filter - int + 范围验证失败', input('id', 'body', 'int', '>200'), null);
$_POST = ['email' => 'test@example.com'];
container("#in.body", $_POST);
test('input + filter - email验证', input('email', 'body', 'email'), 'test@example.com');
$_POST = ['email' => 'invalid-email'];
container("#in.body", $_POST);
test('input + filter - email验证失败', input('email', 'body', 'email'), null);
test();