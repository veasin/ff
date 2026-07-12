<?php
include __DIR__ . "/../../vendor/autoload.php";
use function ff\{test};

if(extension_loaded('curl')){
	$port = 9896;
	$null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
	$proc = proc_open(
		PHP_BINARY . " -S 127.0.0.1:$port -t " . __DIR__ . " " . __DIR__ . "/server.php",
		[1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
		$pipes,
	);
	register_shutdown_function(fn() => is_resource($proc) && proc_terminate($proc));

	$base = "http://127.0.0.1:$port";
	for($i = 0; $i < 50; $i++){
		$sock = @stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 0.1);
		if($sock){ fclose($sock); break; }
		usleep(20_000);
	}

	test('cURL 驱动 GET', function() use($base){
		$r = \ff\http\curl('GET', "$base/json", null, [], []);
		return $r !== null && $r['code'] === 200 && str_contains($r['body'], '"ok":true');
	}, true);
	test('cURL 驱动 POST', function() use($base){
		$r = \ff\http\curl('POST', "$base/echo", '{"x":1}', ['Content-Type: application/json'], []);
		return $r !== null && $r['code'] === 200 && str_contains($r['body'], '\\"x\\":1');
	}, true);
	test('cURL 驱动 原始 headers', function() use($base){
		$r = \ff\http\curl('GET', "$base/json", null, [], []);
		return $r !== null && is_array($r['headers']) && count($r['headers']) > 0;
	}, true);
	test('cURL 驱动 连接失败', function(){
		$r = \ff\http\curl('GET', 'http://127.0.0.1:18765/nonexistent', null, [], ['timeout' => 1]);
		return $r === null;
	}, true);
}

test();
