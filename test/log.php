<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, log, test};

container(null);
container(null, true);
$logs = [];
$testLog = function(string $level, string|array|object $message, array $context) use (&$logs){
	$logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
};
container('^#ext.log.test', $testLog);

log('test message');
test('默认level为info', $logs[0]['level'] ?? '', 'info');
$logs = [];
log('error message', 'error');
test('指定level', $logs[0]['level'] ?? '', 'error');
$logs = [];
log('warning message', 'warning');
test('context为字符串作为level', $logs[0]['level'] ?? '', 'warning');
$logs = [];
log('user {user} login', ['user' => 'admin']);
test('占位符替换', $logs[0]['context']['user'] ?? '', 'admin');
$logs = [];
log(['a' => 1, 'b' => 2]);
test('非string自动json', $logs[0]['message'], ['a' => 1, 'b' => 2]);
$logs = [];
log('error {msg}', ['msg' => 'failed'], 'error');
test('context和level同时存在', $logs[0]['context']['msg'] ?? '', 'failed');
test('context和level同时存在level', $logs[0]['level'] ?? '', 'error');
$logs = [];
log('injected message', ['id' => 123], 'debug');
test('注入日志level', $logs[0]['level'] ?? '', 'debug');
test('注入日志消息', $logs[0]['message'] ?? '', 'injected message');
test('注入日志上下文', $logs[0]['context'] ?? [], ['id' => 123]);
$levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
foreach($levels as $level){
	$logs = [];
	log($level . ' test', $level);
	test("level {$level}", $logs[0]['level'] ?? '', $level);
}
$logs = [];
log('no param');
test('无参数调用', $logs[0]['message'] ?? '', 'no param');
class StringableClass implements \Stringable{
	public function __toString(): string{ return 'from Stringable'; }
}
$logs = [];
log(new StringableClass());
test('Stringable支持', (string)$logs[0]['message'], 'from Stringable');
log('multiple log handlers', 'info');
test('多个handler广播', count($logs), 2);
test();

container(null, true);
