<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{test};
use function ff\output\cli;

// cli() 调用 exit()，需要用子进程测试

$vendor = str_replace('\\', '/', dirname(__DIR__, 2) . '/vendor/autoload.php');

// ——— 基本输出 ———
$pid = getmypid();
$tmp = sys_get_temp_dir() . '/ff_cli_test_' . $pid . '.php';
file_put_contents($tmp, '<?php
require "' . $vendor . '";
use function ff\output\cli;
cli("hello", ["code" => 200]);
');
$out = shell_exec(PHP_BINARY . ' ' . escapeshellarg($tmp) . ' 2>&1');
test('基本输出', $out, 'hello');
unlink($tmp);

// ——— 404 空输出 ———
$tmp = sys_get_temp_dir() . '/ff_cli_test2_' . $pid . '.php';
file_put_contents($tmp, '<?php
require "' . $vendor . '";
use function ff\output\cli;
cli(null, ["code" => 404]);
');
$out = shell_exec(PHP_BINARY . ' ' . escapeshellarg($tmp) . ' 2>&1');
test('404 空输出', $out ?? '', '');
unlink($tmp);

test();
