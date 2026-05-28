<?php
include __DIR__ . "/../_boot.php";

use function nx\{container, from, test};
use function nx\output\http;

container('#in.input', ['protocol' => 'HTTP/1.1']);

$response = ['body' => 'hello', 'code' => 200, 'headers' => ['Content-Type' => 'text/plain']];
ob_start();
http($response);
$result = ob_get_clean();
test('output_http 基本输出', $result, 'hello');

$response = ['body' => null, 'code' => 404];
ob_start();
http($response);
$result = ob_get_clean();
test('output_http 404无body', $result, '');

$response = ['body' => 'error', 'code' => 500, 'message' => 'Server Error'];
ob_start();
http($response);
$result = ob_get_clean();
test('output_http 自定义消息', $result, 'error');
test();

