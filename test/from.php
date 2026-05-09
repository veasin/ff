<?php
require __DIR__ . '/../vendor/autoload.php';

use function nx\{container, from, test};

container("#in.input", [
	'method' => 'get',
	'uri' => '/users/123?name=test',
	'params' => null,
]);
container('#in.params', ['id' => '123', 'name' => 'test']);
$_GET = ['name' => 'test'];
test('from source - 完整数组', from(null, 'input'), fn($v) => is_array($v) && isset($v['method']) && isset($v['uri']));
test('from source - method', from('method', 'input'), 'get');
test('from source - uri', from('uri', 'input'), '/users/123?name=test');
test('from source - params (web)', from('params', 'input'), null);
test('params source - 容器有值', from('id', 'params'), '123');
test('params source - 容器无值', from('name', 'params'), 'test');
container("#in.input", [
	'method' => 'cli',
	'uri' => 'cli.php --name=test --id=123',
	'params' => ['name' => 'test', 'id' => '123'],
]);
test('from source - cli mode', from('method', 'input'), 'cli');
test('from source - cli uri', from('uri', 'input'), 'cli.php --name=test --id=123');
test('from source - cli params', from('params', 'input'), fn($v) => is_array($v) && isset($v['name']));
test('query source', from('name', 'query'), 'test');
test('name=null 返回数组', from(null, 'query'), ['name' => 'test']);
test('from 使用数组作为source', from('id', ['id' => '456', 'name' => 'test']), '456');
test('from 使用数组作为source返回整个数组', from(null, ['id' => '456', 'name' => 'test']), ['id' => '456', 'name' => 'test']);
test('from 数组参数', from(['id', 'name'], 'query'), ['id' => null, 'name' => 'test']);

container('#in.headers', ['content-type' => 'application/x-www-form-urlencoded']);
container('#in.raw', 'name=test&id=123');
container('#in.body', null);
test('from body - x-www-form-urlencoded', from('name'), 'test');
test('from body - x-www-form-urlencoded id', from('id'), '123');

container('#in.headers', ['content-type' => 'application/json']);
container('#in.raw', '{"name":"test","id":456}');
container('#in.body', null);
test('from body - application/json', from('name'), 'test');
test('from body - application/json id', from('id'), 456);

container('#in.content', [
    'text/xml' => fn($raw) => ['parsed' => 'xml', 'raw' => $raw],
    'default' => fn($raw) => ['parsed' => 'custom', 'raw' => $raw],
]);
container('#in.headers', ['content-type' => 'text/xml']);
container('#in.raw', '<root/>');
container('#in.body', null);
test('from body - custom content-type', from('parsed'), 'xml');

container('#in.headers', ['content-type' => 'text/plain']);
container('#in.raw', 'custom-default');
container('#in.body', null);
test('from body - custom default', from(null), ['parsed' => 'custom', 'raw' => 'custom-default', 'RAW' => 'custom-default']);