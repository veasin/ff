<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{ext, test};

// ——— file 读取内容 ———
$testFile = sys_get_temp_dir() . '/ff_file_test_' . uniqid() . '.txt';
file_put_contents($testFile, 'hello file');

$result = ext('out.type', 'file', false, ['file' => $testFile, 'code' => 200]);
test('file 读取内容', $result[0], 'hello file');

// ——— file 不存在 ———
$result = ext('out.type', 'file', false, ['file' => '/path/to/nonexistent', 'code' => 200]);
test('file 不存在 code', $result[1]['code'], 404);

// ——— file Content-Type ———
$result = ext('out.type', 'file', false, ['file' => $testFile, 'code' => 200]);
test('file Content-Type', isset($result[1]['headers']['Content-Type']), true);

// ——— file 下载模式 ———
$result = ext('out.type', 'file', true, ['file' => $testFile, 'code' => 200]);
test('file 下载 Content-Disposition', ($result[1]['headers']['Content-Disposition'] ?? ''), 'attachment; filename="' . basename($testFile) . '"');
test('file 下载 Content-Length', isset($result[1]['headers']['Content-Length']), true);

// ——— file 无 path ———
$result = ext('out.type', 'file', false, ['code' => 200]);
test('file 无 path code', $result[1]['code'], 404);

unlink($testFile);

test();
