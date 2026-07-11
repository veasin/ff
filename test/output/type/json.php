<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{ext, test};

// ——— json format 直接调用 ———
$result = ext('out.type', 'json', ['a' => 1], ['code' => 200]);
test('json 基本编码', $result[0], json_encode(['a' => 1]));
test('json Content-Type', ($result[1]['headers'] ?? [])['Content-Type'] ?? null, 'application/json; charset=UTF-8');

// ——— json pretty ———
$data = ['a' => 1];
$result = ext('out.type', 'json', $data, ['code' => 200, 'pretty' => true]);
test('json pretty', $result[0], json_encode(['a' => 1], JSON_PRETTY_PRINT));

// ——— json null data ———
$result = ext('out.type', 'json', null, ['code' => 204]);
test('json null body', $result[0], null);

// ——— json 编码失败（无效 UTF-8） ———
$result = ext('out.type', 'json', "\xB1\x31", ['code' => 200]);
test('json 编码失败 code', $result[1]['code'], 500);
test('json 编码失败 message', is_string($result[1]['message'] ?? null), true);

// ——— json 保留已有 headers ———
$result = ext('out.type', 'json', 123, ['code' => 200, 'headers' => ['X-Custom' => 'val']]);
test('json 保留已有 headers', ($result[1]['headers'] ?? [])['X-Custom'] ?? null, 'val');

test();
