<?php
include __DIR__ . "/../../vendor/autoload.php";

use function nx\test;

$response = ['body' => ['name' => 'test'], 'code' => 200, 'headers' => []];
$formats = ['http' => '\nx\output\http'];

ob_start();
\nx\output\json($response, $formats);
$result = ob_get_clean();
$expected = json_encode(['name' => 'test']);
test('output_json 基本输出', $result, $expected);

$response = ['body' => ['a' => 1], 'pretty' => true, 'code' => 200, 'headers' => []];
ob_start();
\nx\output\json($response, $formats);
$result = ob_get_clean();
test('output_json pretty输出', $result, json_encode(['a' => 1], JSON_PRETTY_PRINT));

$response = ['body' => null, 'code' => 200, 'headers' => []];
ob_start();
\nx\output\json($response, $formats);
$result = ob_get_clean();
test('output_json null body', $result, '');
test();

