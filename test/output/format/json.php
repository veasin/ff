<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\test;
use function nx\output\format\json;

$response = ['body' => ['name' => 'test'], 'code' => 200, 'headers' => []];
$result = json($response);
test('json 基本编码', $result['body'], json_encode(['name' => 'test']));
test('json Content-Type', $result['headers']['Content-Type'] ?? null, 'application/json; charset=UTF-8');

$response = ['body' => ['a' => 1], 'pretty' => true, 'code' => 200, 'headers' => []];
$result = json($response);
test('json pretty', $result['body'], json_encode(['a' => 1], JSON_PRETTY_PRINT));

$response = ['body' => null, 'code' => 200, 'headers' => []];
$result = json($response);
test('json null body', $result['body'], null);
test('json null body 无 Content-Type', ($result['headers'] ?? [])['Content-Type'] ?? null, null);

$response = ['body' => "\xB1\x31", 'code' => 200, 'headers' => []];
$result = json($response);
test('json 编码失败 code', $result['code'], 500);
test('json 编码失败 message', is_string($result['message'] ?? null), true);

$response = ['body' => 123, 'code' => 200, 'headers' => ['X-Custom' => 'val']];
$result = json($response);
test('json 保留已有 headers', $result['headers']['X-Custom'] ?? null, 'val');

test();
