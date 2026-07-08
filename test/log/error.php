<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, log, test};

container(null);
container(null, true);

$tmpLog = sys_get_temp_dir() . '/ff-test-error-log-' . uniqid();
ini_set('error_log', $tmpLog);
container('^#ext.log.error', \ff\log\error(...));

$read = function() use ($tmpLog){
	$raw = file_get_contents($tmpLog);
	$raw = preg_replace('/^\[[^\]]+\]\s/', '', $raw);  // strip timestamp prefix
	$raw = str_replace("\r\n", "\n", $raw);              // normalize line endings
	return $raw;
};

log('plain message');
test('简单消息', $read(), "[info] plain message\n");
file_put_contents($tmpLog, '');

log('error message', 'error');
test('指定level', $read(), "[error] error message\n");
file_put_contents($tmpLog, '');

log('user {user} login', ['user' => 'admin']);
test('占位符替换', $read(), "[info] user admin login\n");
file_put_contents($tmpLog, '');

log('error {msg}', ['msg' => 'failed'], 'error');
test('context+level', $read(), "[error] error failed\n");
file_put_contents($tmpLog, '');

log(['a' => 1, 'b' => 2]);
test('非string自动json', $read(), "[info] {\"a\":1,\"b\":2}\n");
file_put_contents($tmpLog, '');

log('multiple {a} {b}', ['a' => 'x', 'b' => 'y']);
test('多占位符', $read(), "[info] multiple x y\n");
file_put_contents($tmpLog, '');

log('no context');
test('无context', $read(), "[info] no context\n");
file_put_contents($tmpLog, '');

$levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
foreach($levels as $level){
	log($level . ' test', $level);
	test("level {$level}", $read(), "[{$level}] {$level} test\n");
	file_put_contents($tmpLog, '');
}

test();

unlink($tmpLog);
ini_set('error_log', '');
container(null, true);
