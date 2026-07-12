<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\from\body;

container('#in.headers', ['content-type' => 'application/json']);
container('#in.raw', '{"name":"test","id":42}');
test('body - JSON解析', body(), ['name' => 'test', 'id' => 42, 'RAW' => '{"name":"test","id":42}']);
test('body - JSON取值', body()['name'], 'test');
test('body - RAW字段', body()['RAW'], '{"name":"test","id":42}');

container(null);
container('#in.headers', ['content-type' => 'application/x-www-form-urlencoded']);
container('#in.raw', 'name=hello&id=99');
test('body - form解析', body()['name'], 'hello');
test('body - form id', body()['id'], '99');

container(null);
container('#in.headers', ['content-type' => 'text/xml']);
container('#in.raw', '<root/>');
test('body - 未知类型空数组', body(), ['RAW' => '<root/>']);

container(null);
container('#in.headers', ['content-type' => 'application/json']);
container('#in.raw', '{"a":1}');
$body1 = body();
container('#in.body', null);
container('#in.raw', '{"b":2}');
$body2 = body();
test('body - 容器清空后重新解析', $body2['b'], 2);
test('body - 旧值不再存在', $body1['b'] ?? null, null);

container(null);
container('#in.headers', ['content-type' => 'text/plain']);
container('#in.raw', 'plain data');
container('#in.content', [
	'text/plain' => fn($raw) => ['parsed' => $raw, 'type' => 'custom'],
]);
test('body - 自定义解析器', body()['parsed'], 'plain data');
test('body - 自定义解析器type', body()['type'], 'custom');

container(null);
container('#in.headers', ['content-type' => 'multipart/form-data']);
test('body - multipart用$_POST', body(), fn($v) => isset($v['RAW']));

test();
