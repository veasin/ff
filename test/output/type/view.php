<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{ext, test};

// ——— view format 直接调用 ———
$viewFile = sys_get_temp_dir() . '/ff_view_test_' . uniqid() . '.phtml';
file_put_contents($viewFile, '<?php echo $name; ?>');

$result = ext('out.type', 'view', ['name' => 'test'], ['file' => $viewFile, 'code' => 200]);
test('view 模板渲染', $result[0], 'test');

unlink($viewFile);

// ——— view 多变量 ———
$viewFile2 = sys_get_temp_dir() . '/ff_view_test2_' . uniqid() . '.phtml';
file_put_contents($viewFile2, '<?php echo $title . ":" . $count; ?>');

$result = ext('out.type', 'view', ['title' => 'items', 'count' => 3], ['file' => $viewFile2, 'code' => 200]);
test('view 多变量', $result[0], 'items:3');

unlink($viewFile2);

// ——— view 无 file 返回原始 data ———
$result = ext('out.type', 'view', [], []);
test('view 无 file 返回 data', $result[0], []);

// ——— view file 不存在返回原始 data ———
$result = ext('out.type', 'view', [], ['file' => '/nonexistent']);
test('view 不存在文件 返回 data', $result[0], []);

test();
