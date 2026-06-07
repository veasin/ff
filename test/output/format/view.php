<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\test;
use function nx\output\format\view;

$viewFile = __DIR__ . '/view_test.phtml';
file_put_contents($viewFile, '<?php echo $name; ?>');

$response = ['body' => ['name' => 'test'], 'code' => 200, 'headers' => [], 'file' => $viewFile];
$result = view($response);
test('view 模板渲染', $result['body'], 'test');

$viewFile2 = __DIR__ . '/view_test2.phtml';
file_put_contents($viewFile2, '<?php echo $title . ":" . $count; ?>');

$response = ['body' => ['title' => 'items', 'count' => 3], 'code' => 200, 'headers' => [], 'file' => $viewFile2];
$result = view($response);
test('view 多变量', $result['body'], 'items:3');

test();

unlink($viewFile);
unlink($viewFile2);
