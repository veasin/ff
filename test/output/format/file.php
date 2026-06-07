<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\test;
use function nx\output\format\file;

$testFile = __DIR__ . '/file_test.txt';
file_put_contents($testFile, 'hello file');

$response = ['body' => null, 'code' => 200, 'headers' => [], 'file' => $testFile];
$result = file($response);
test('file 读取内容', $result['body'], 'hello file');

$response = ['body' => null, 'code' => 200, 'headers' => [], 'file' => '/path/to/nonexistent'];
$result = file($response);
test('file 不存在 code', $result['code'], 404);
test('file 不存在 body', $result['body'], '');

$response = ['body' => null, 'code' => 200, 'headers' => [], 'file' => $testFile];
$result = file($response);
test('file Content-Type', isset($result['headers']['Content-Type']), true);

$response = ['body' => true, 'code' => 200, 'headers' => [], 'file' => $testFile];
$result = file($response);
test('file 下载 Content-Disposition', ($result['headers']['Content-Disposition'] ?? ''), 'attachment; filename="file_test.txt"');
test('file 下载 Content-Length', isset($result['headers']['Content-Length']), true);

$response = ['body' => null, 'code' => 200, 'headers' => []];
$result = file($response);
test('file 无 path code', $result['code'], 404);

test();
unlink($testFile);
