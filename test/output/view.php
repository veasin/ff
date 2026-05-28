<?php
include __DIR__ . "/../_boot.php";

use function nx\test;

$viewFile = __DIR__ . '/view_test.php';
file_put_contents($viewFile, '<?php echo $name; ?>');

$response = ['body' => ['name' => 'test'], 'code' => 200, 'headers' => [], 'file' => $viewFile];
$formats = ['http' => '\nx\output\http'];

ob_start();
\nx\output\view($response, $formats);
$result = ob_get_clean();
test('output_view 模板渲染', $result, 'test');

test();
unlink($viewFile);

