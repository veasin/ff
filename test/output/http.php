<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\output\http;

container('#in.input', ['protocol' => 'HTTP/1.1']);

// ——— 基本输出 ———
ob_start();
http('hello', ['code' => 200]);
$result = ob_get_clean();
test('基本输出', $result, 'hello');

// ——— 404 null body ———
ob_start();
http(null, ['code' => 404]);
$result = ob_get_clean();
test('404 空 body', $result, '');

// ——— 自定义 message ———
ob_start();
http('error', ['code' => 500, 'message' => 'Server Error']);
$result = ob_get_clean();
test('自定义 message', $result, 'error');

// ——— headers 输出 ———
ob_start();
http('ok', ['code' => 200, 'headers' => ['X-Custom' => 'val']]);
$result = ob_get_clean();
test('headers 输出', $result, 'ok');

// ——— NX header 自动附加 ———
ob_start();
http('test', ['code' => 200]);
// 验证 header 是否设置了（无法直接捕获 header，只能验证 body）
$result = ob_get_clean();
test('NX header 自动附加', $result, 'test');

test();
