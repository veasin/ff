<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, http, test};

container(null);
container(null, true);

// ── Mock driver（模拟 server 行为，无网络开销）──
$port = 9899;
$base = "http://127.0.0.1:$port";
$mockDriver = function($method, $url, $body, $headers, $config) use($port){
	$parts = parse_url($url);
	$path = $parts['path'] ?? '/';
	$query = $parts['query'] ?? null;
	if(($parts['port'] ?? 80) != $port) return null;

	if(preg_match('#^/status/(\d+)$#', $path, $m)){
		$c = (int)$m[1];
		return ['body' => json_encode(['code' => $c]), 'code' => $c, 'headers' => ['Content-Type: application/json'], 'message' => ''];
	}
	if($path === '/redirect'){
		return ['body' => json_encode(['method' => $method, 'path' => '/json', 'ok' => true]), 'code' => 200, 'headers' => ['Content-Type: application/json'], 'message' => 'OK'];
	}
	if($path === '/slow') return null;
	if($path === '/json'){
		$isHead = strtoupper($method) === 'HEAD';
		return [
			'body' => $isHead ? '' : json_encode(['method' => $method, 'path' => '/json', 'ok' => true]),
			'code' => 200,
			'headers' => ['Content-Type: application/json'],
			'message' => 'OK',
		];
	}
	if($path === '/query'){
		parse_str($query ?? '', $params);
		return ['body' => json_encode(['query' => $params]), 'code' => 200, 'headers' => ['Content-Type: application/json'], 'message' => 'OK'];
	}
	if($path === '/echo'){
		$ct = '';
		foreach($headers as $h) if(stripos($h, 'content-type:') === 0) $ct = trim(substr($h, 13));
		return ['body' => json_encode(['method' => $method, 'body' => $body, 'content_type' => $ct]), 'code' => 200, 'headers' => ['Content-Type: application/json'], 'message' => 'OK'];
	}
	if($path === '/headers'){
		$parsed = [];
		foreach($headers as $h) if(str_contains($h, ':')){[$k, $v] = explode(':', $h, 2); $parsed[strtolower(trim($k))] = trim($v);}
		return ['body' => json_encode(['headers' => $parsed]), 'code' => 200, 'headers' => ['Content-Type: application/json'], 'message' => 'OK'];
	}
	if($path === '/multi-header'){
		return ['body' => json_encode(['ok' => true]), 'code' => 200, 'headers' => ['X-Custom: val1', 'X-Custom: val2'], 'message' => 'OK'];
	}
	if($path === '/html'){
		return ['body' => '<html><body><h1>Hello</h1></body></html>', 'code' => 200, 'headers' => ['Content-Type: text/html; charset=utf-8'], 'message' => 'OK'];
	}
	if($path === '/form'){
		return ['body' => 'foo=bar&num=42', 'code' => 200, 'headers' => ['Content-Type: application/x-www-form-urlencoded'], 'message' => 'OK'];
	}
	return ['body' => json_encode(['error' => 'not found']), 'code' => 404, 'headers' => ['Content-Type: application/json'], 'message' => 'Not Found'];
};

// 注册 mock 驱动到 ext，设为默认
container('^#ext.http.mock', $mockDriver);
container('#http', ['driver' => 'mock', 'timeout' => 30]);

// ── 核心测试 ──

// 1. GET 请求
test('GET 请求', function() use($base){
	return http("GET $base/json");
}, function($r){
	return is_array($r) && isset($r['code'], $r['body'], $r['headers'])
		&& $r['code'] === 200 && ($r['body']['ok'] ?? null) === true;
});

// 2. 无空格默认 GET
test('无空格默认 GET', function() use($base){
	return http("$base/json");
}, function($r){
	return $r['code'] === 200;
});

// 3. POST 数组 body 自动 JSON
test('POST 数组 body 自动 JSON', function() use($base){
	return http("POST $base/echo", ['name' => 'John', 'age' => 30]);
}, function($r){
	return $r['code'] === 200
		&& str_contains($r['body']['content_type'] ?? '', 'application/json')
		&& json_decode($r['body']['body'] ?? '', true) === ['name' => 'John', 'age' => 30];
});

// 4. POST 字符串 body
test('POST 字符串 body', function() use($base){
	return http("POST $base/echo", 'raw string body');
}, function($r){
	return $r['code'] === 200 && ($r['body']['body'] ?? '') === 'raw string body';
});

// 5. PUT 请求
test('PUT 请求', function() use($base){
	return http("PUT $base/echo", ['key' => 'val']);
}, function($r){
	return $r['code'] === 200 && ($r['body']['method'] ?? '') === 'PUT';
});

// 6. PATCH 请求
test('PATCH 请求', function() use($base){
	return http("PATCH $base/echo", ['patch' => true]);
}, function($r){
	return $r['code'] === 200 && ($r['body']['method'] ?? '') === 'PATCH';
});

// 7. DELETE 请求
test('DELETE 请求', function() use($base){
	return http("DELETE $base/echo");
}, function($r){
	return $r['code'] === 200 && ($r['body']['method'] ?? '') === 'DELETE';
});

// 8. HEAD 请求（无 body）
test('HEAD 请求', function() use($base){
	return http("HEAD $base/json");
}, function($r){
	return $r['code'] === 200 && $r['body'] === '';
});

// 9. OPTIONS 请求
test('OPTIONS 请求', function() use($base){
	return http("OPTIONS $base/json");
}, function($r){
	return $r['code'] === 200;
});

// 10. Query 参数
test('Query 参数', function() use($base){
	return http("GET $base/query", query: ['page' => 1, 'limit' => 20]);
}, function($r){
	$q = $r['body']['query'] ?? [];
	return ($q['page'] ?? null) === '1' && ($q['limit'] ?? null) === '20';
});

// 11. 自定义 header（关联格式）
test('自定义 header 关联格式', function() use($base){
	return http("GET $base/headers", headers: ['X-Test' => 'hello', 'Authorization' => 'Bearer tok']);
}, function($r){
	$h = $r['body']['headers'] ?? [];
	return ($h['x-test'] ?? null) === 'hello'
		&& ($h['authorization'] ?? null) === 'Bearer tok';
});

// 12. 自定义 header（扁平格式）
test('自定义 header 扁平格式', function() use($base){
	return http("GET $base/headers", headers: ['X-Flat: value1', 'X-Another: value2']);
}, function($r){
	$h = $r['body']['headers'] ?? [];
	return ($h['x-flat'] ?? null) === 'value1'
		&& ($h['x-another'] ?? null) === 'value2';
});

// 13. mode='body' 返回解码后 body
test("mode='body' 返回解码后 body", function() use($base){
	return http("GET $base/json", mode: 'body');
}, function($r){
	return is_array($r) && ($r['ok'] ?? null) === true;
});

// 13b. decode='raw' 保持原始字符串
test("decode='raw' 保持原始字符串", function() use($base){
	return http("GET $base/json", mode: 'body', option: ['decode' => 'raw']);
}, function($r){
	return is_string($r) && str_contains($r, '"ok":true');
});

// 14. mode='code'
test("mode='code' 返回状态码", function() use($base){
	return http("GET $base/json", mode: 'code');
}, 200);

// 15. mode='ok' 成功
test("mode='ok' 2xx 返回 true", function() use($base){
	return http("GET $base/json", mode: 'ok');
}, true);

// 16. mode='ok' 非 2xx
test("mode='ok' 非 2xx 返回 false", function() use($base){
	return http("GET $base/status/404", mode: 'ok');
}, false);

// 17. mode='headers'
test("mode='headers' 返回 headers", function() use($base){
	return http("GET $base/json", mode: 'headers');
}, function($r){
	return is_array($r) && isset($r['content-type']);
});

// 18. Content-Type: application/x-www-form-urlencoded
test('x-www-form-urlencoded encoding', function() use($base){
	return http("POST $base/echo", ['a' => 1, 'b' => 2],
		headers: ['Content-Type: application/x-www-form-urlencoded']);
}, function($r){
	return $r['code'] === 200
		&& str_contains($r['body']['content_type'] ?? '', 'x-www-form-urlencoded')
		&& ($r['body']['body'] ?? '') === 'a=1&b=2';
});

// 19. 完整返回自动解码 JSON
test('完整返回自动解码 JSON', function() use($base){
	return http("GET $base/json");
}, function($r){
	return is_array($r['body']) && ($r['body']['ok'] ?? null) === true;
});

// 20. 响应非 JSON 不解码
test('响应非 JSON 保持字符串', function() use($base){
	return http("GET $base/html", mode: 'body');
}, function($r){
	return is_string($r) && str_contains($r, '<h1>Hello</h1>');
});

// 21. 404 状态码
test('404 状态码', function() use($base){
	return http("GET $base/status/404", mode: 'code');
}, 404);

// 22. 500 状态码
test('500 状态码', function() use($base){
	return http("GET $base/status/500", mode: 'code');
}, 500);

// 23. 完整返回包含 message
test('完整返回结构', function() use($base){
	return http("GET $base/status/404");
}, function($r){
	return isset($r['body'], $r['code'], $r['headers'], $r['message'])
		&& $r['code'] === 404;
});

// 24. $option body 覆盖
test('$option body 覆盖', function() use($base){
	return http("POST $base/echo", option: ['body' => ['override' => true]]);
}, function($r){
	return $r['code'] === 200
		&& str_contains($r['body']['body'] ?? '', '"override":true');
});

// 25. $option query 覆盖
test('$option query 覆盖', function() use($base){
	return http("GET $base/query", option: ['query' => ['from' => 'option']]);
}, function($r){
	return ($r['body']['query']['from'] ?? null) === 'option';
});

// 26. $option headers 合并
test('$option headers 合并', function() use($base){
	return http("GET $base/headers",
		headers: ['X-A: from-arg'],
		option: ['headers' => ['X-B: from-option']]
	);
}, function($r){
	$h = $r['body']['headers'] ?? [];
	return ($h['x-a'] ?? null) === 'from-arg'
		&& ($h['x-b'] ?? null) === 'from-option';
});

// 27. 驱动返回 null（模拟连接失败）
test('驱动返回 null', function() use($base){
	return http("GET $base/slow");
}, null);

// 28. 多值响应 header
test('多值响应 header', function() use($base){
	$r = http("GET $base/multi-header", mode: 'headers');
	return $r['x-custom'] ?? null;
}, function($v){
	return is_array($v) && $v === ['val1', 'val2'];
});

// 29. 自定义驱动名
test('自定义驱动名', function() use($base){
	$called = false;
	container('^#ext.http.custom_test', function($method, $url, $body, $headers, $config) use(&$called){
		$called = true;
		return ['body' => ['custom' => true], 'code' => 201, 'headers' => ['X-Custom: yes'], 'message' => 'Created'];
	});
	$r = http("POST $base/json", ['test' => 1], option: ['driver' => 'custom_test']);
	container('^#ext.http.custom_test', null);
	return $called && $r['code'] === 201 && ($r['body']['custom'] ?? null) === true;
}, true);

// 30. $option timeout（驱动返回 null）
test('$option timeout', function() use($base){
	return http("GET $base/slow", option: ['timeout' => 1]);
}, null);

// ── encode/decode 测试 ──

// 31. encode='json' 显式 JSON
test("encode='json'", function() use($base){
	return http("POST $base/echo", ['a' => 1], option: ['encode' => 'json']);
}, function($r){
	return $r['code'] === 200
		&& str_contains($r['body']['content_type'] ?? '', 'application/json')
		&& ($r['body']['body'] ?? '') === '{"a":1}';
});

// 32. encode='form' 强制 urlencoded（响应是 JSON，需显式 decode）
test("encode='form'", function() use($base){
	return http("POST $base/echo", ['a' => 1, 'b' => 2], option: ['encode' => 'form', 'decode' => 'json']);
}, function($r){
	return $r['code'] === 200
		&& str_contains($r['body']['content_type'] ?? '', 'x-www-form-urlencoded')
		&& ($r['body']['body'] ?? '') === 'a=1&b=2';
});

// 33. encode Closure 自定义编码器
test('encode Closure', function() use($base){
	return http("POST $base/echo", ['name' => 'test'], option: [
		'encode' => fn($body, $ctx) => 'custom:'.json_encode($body),
		'decode' => 'json',
	]);
}, function($r){
	return $r['code'] === 200 && ($r['body']['body'] ?? '') === 'custom:{"name":"test"}';
});

// 34. encode Closure 返回 null 不发送 body
test('encode Closure 不发送', function() use($base){
	return http("POST $base/json", ['x' => 1], option: [
		'encode' => fn($body, $ctx) => null,
		'decode' => 'json',
	]);
}, function($r){
	return $r['code'] === 200 && ($r['body']['method'] ?? '') === 'POST';
});

// 35. decode='form' 解析 form 响应
test("decode='form'", function() use($base){
	return http("GET $base/form", mode: 'body', option: ['decode' => 'form']);
}, function($r){
	return is_array($r) && ($r['foo'] ?? null) === 'bar' && ($r['num'] ?? null) === '42';
});

// 36. decode Closure 自定义解码器
test('decode Closure', function() use($base){
	return http("GET $base/json", mode: 'body', option: [
		'decode' => fn($raw, $ctx) => ['from_raw' => $raw],
	]);
}, function($r){
	return is_array($r) && isset($r['from_raw']);
});

// ── Edge case 测试 ──

// 37. __toString 对象 body
test('__toString 对象 body', function() use($base){
	$obj = new class('Hello toString') {
		public function __construct(public string $msg) {}
		public function __toString(): string { return $this->msg; }
	};
	return http("POST $base/echo", $obj);
}, function($r){
	return $r['code'] === 200 && ($r['body']['body'] ?? '') === 'Hello toString';
});

// 38. Iterator (Traversable) body → 自动 JSON 编码
test('Iterator body 自动 JSON', function() use($base){
	$iterator = new \ArrayIterator(['a' => 1, 'b' => 2]);
	return http("POST $base/echo", $iterator);
}, function($r){
	return $r['code'] === 200
		&& str_contains($r['body']['content_type'] ?? '', 'application/json')
		&& ($r['body']['body'] ?? '') === '{"a":1,"b":2}';
});

test();

container(null, true);
