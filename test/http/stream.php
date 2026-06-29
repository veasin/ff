<?php
include __DIR__ . "/../../vendor/autoload.php";
use function ff\{test};

$port = 9897;
$null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$proc = proc_open(
	PHP_BINARY . " -S 127.0.0.1:$port -t " . __DIR__ . " " . __DIR__ . "/server.php",
	[1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
	$pipes,
);
register_shutdown_function(fn() => is_resource($proc) && proc_terminate($proc));

$base = "http://127.0.0.1:$port";
for($i = 0; $i < 20; $i++){
	$r = @file_get_contents("$base/json", false, stream_context_create(['http' => ['timeout' => 1]]));
	if($r !== false) break;
	usleep(100_000);
}

test('Stream 驱动 GET', function() use($base){
	$r = \ff\http\stream('GET', "$base/json", null, [], []);
	return $r !== null && $r['code'] === 200 && str_contains($r['body'], '"ok":true');
}, true);
test('Stream 驱动 POST', function() use($base){
	$r = \ff\http\stream('POST', "$base/echo", '{"x":1}', ['Content-Type: application/json'], []);
	return $r !== null && $r['code'] === 200 && str_contains($r['body'], '\\"x\\":1');
}, true);
test('Stream 驱动 原始 headers', function() use($base){
	$r = \ff\http\stream('GET', "$base/json", null, [], []);
	return $r !== null && is_array($r['headers']) && count($r['headers']) > 0;
}, true);
test('Stream 驱动 连接失败', function(){
	$r = \ff\http\stream('GET', 'http://127.0.0.1:18766/nonexistent', null, [], ['timeout' => 1]);
	return $r === null;
}, true);

test();
