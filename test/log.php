<?php
include __DIR__ . "/_boot.php";

use function nx\{container, log, test};

container(null);
class TestLogger{
	public array $logs = [];
	public function log(string $level, string|object|array $message, array $context = []): void{
		$this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
	}
}
$testLogger = new TestLogger();
container('#log', $testLogger);
log('test message');
test('默认level为info', $testLogger->logs[0]['level'] ?? '', 'info');
$testLogger->logs = [];
log('error message', 'error');
test('指定level', $testLogger->logs[0]['level'] ?? '', 'error');
$testLogger->logs = [];
log('warning message', 'warning');
test('context为字符串作为level', $testLogger->logs[0]['level'] ?? '', 'warning');
$testLogger->logs = [];
log('user {user} login', ['user' => 'admin']);
test('占位符替换', $testLogger->logs[0]['context']['user'] ?? '', 'admin');
$testLogger->logs = [];
log(['a' => 1, 'b' => 2]);
test('非string自动json', $testLogger->logs[0]['message'], ['a' => 1, 'b' => 2]);
$testLogger->logs = [];
log('error {msg}', ['msg' => 'failed'], 'error');
test('context和level同时存在', $testLogger->logs[0]['context']['msg'] ?? '', 'failed');
test('context和level同时存在level', $testLogger->logs[0]['level'] ?? '', 'error');
$testLogger->logs = [];
log('injected message', ['id' => 123], 'debug');
test('注入PSRLogger', $testLogger->logs[0]['level'] ?? '', 'debug');
test('注入PSRLogger消息', $testLogger->logs[0]['message'] ?? '', 'injected message');
test('注入PSRLogger上下文', $testLogger->logs[0]['context'] ?? [], ['id' => 123]);
$levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
foreach($levels as $level){
	$testLogger->logs = [];
	log($level . ' test', $level);
	test("level {$level}", $testLogger->logs[0]['level'] ?? '', $level);
}
$testLogger->logs = [];
log('no param');
test('无参数调用', $testLogger->logs[0]['message'] ?? '', 'no param');
class StringableClass implements \Stringable{
	public function __toString(): string{ return 'from Stringable'; }
}
$stringableLog = new class{
	public string $message = '';
	public function log(string $level, string|object|array $message, array $context = []): void{
		$this->message = $message instanceof \Stringable ? (string)$message : gettype($message);
	}
};
container('#log', $stringableLog);
log(new StringableClass());
test('Stringable支持', $stringableLog->message, 'from Stringable');
container(null);
$closureCalled = false;
$closureLog = [];
$closureLogger = function(string $level, string|array|object $message, array $context) use (&$closureCalled, &$closureLog){
	$closureCalled = true;
	$closureLog = ['level' => $level, 'message' => $message, 'context' => $context];
};
container('#log', $closureLogger);
log('closure test', ['id' => 456], 'warning');
test('闭包logger调用', $closureCalled, true);
test('闭包logger参数', $closureLog['level'] ?? '', 'warning');
test('闭包logger消息', $closureLog['message'] ?? '', 'closure test');
test('闭包logger上下文', $closureLog['context'] ?? [], ['id' => 456]);
test();