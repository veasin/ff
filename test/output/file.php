<?php
include __DIR__ . "/../../vendor/autoload.php";

use function nx\{container, output, test};

// 测试1：展示文件
$testFile = __DIR__ . '/file_test.txt';
file_put_contents($testFile, 'hello file');

output(null, 'file', $testFile);
ob_start();
container('#out.render*');
$result = ob_get_clean();
test('file 展示模式', $result, 'hello file');

// 测试2：文件不存在返回404（body为空）
output(null, 'file', '/path/to/nonexistent');
ob_start();
container('#out.render*');
$body = ob_get_clean();
test('file 404 空内容', $body, '');

// 测试3：下载模式
output(true, 'file', $testFile);
ob_start();
container('#out.render*');
$content = ob_get_clean();
test('file 下载内容', $content, 'hello file');

unlink($testFile);

// 测试4：通过数组传参
$testFile2 = __DIR__ . '/file_test2.txt';
file_put_contents($testFile2, 'array param');

output(null, 'file', ['file' => $testFile2]);
ob_start();
container('#out.render*');
$result2 = ob_get_clean();
test('file 数组传参', $result2, 'array param');

unlink($testFile2);