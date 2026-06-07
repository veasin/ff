<?php
include __DIR__ . "/../../vendor/autoload.php";

use function nx\{container, test};
use function nx\output\http;

container('#in.input', ['protocol' => 'HTTP/1.1']);

$response = ['body' => 'hello', 'code' => 200, 'headers' => ['Content-Type' => 'text/plain']];
ob_start();
http($response);
$result = ob_get_clean();
test('http 基本输出', $result, 'hello');

$response = ['body' => null, 'code' => 404];
ob_start();
http($response);
$result = ob_get_clean();
test('http 404 空 body', $result, '');

$response = ['body' => 'error', 'code' => 500, 'message' => 'Server Error'];
ob_start();
http($response);
$result = ob_get_clean();
test('http 自定义消息', $result, 'error');

$captured = null;
container('#out.callback', function($r) use (&$captured){ $captured = $r['body']; });
$response = ['body' => 'cb', 'code' => 200];
ob_start();
http($response);
$output = ob_get_clean();
test('http callback 触发', $captured, 'cb');
test('http callback 不 echo', $output, '');

container('#out.callback', null);

test();
