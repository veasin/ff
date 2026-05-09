<?php
declare(strict_types=1);
namespace nx;
/**
 * 从指定来源获取原始值
 * @param string|null|array $name   键名，null 时返回整个来源
 * @param string|array      $source 来源名称或直接使用数组 query|cookie|file|params|header|input|body
 * @return mixed
 * @see input() 推荐使用此函数获取带验证的输入
 * @internal请使用 input() 替代，from() 仅返回原始数据无验证
 */
function from(string|null|array $name, string|array $source = 'body'): mixed{
	if(is_array($source)){
		return match (true) {
			is_array($name) => array_fill_keys($name, null),
			$name === null => $source,
			default => $source[$name] ?? null,
		};
	}
	if(is_array($name)){
		$result = [];
		foreach($name as $key){
			$result[$key] = from($key, $source);
		}
		return $result;
	}
	static $getInput = function(){
		$result = PHP_SAPI === 'cli'
			? [
				'method' => 'cli',
				'protocol' => null,
				'uri' => implode(' ', $_SERVER['argv']),
				'params' => args(array_slice($_SERVER['argv'], 1)) ?? [],
			]
			: [
				'method' => strtolower($_SERVER['REQUEST_METHOD'] ?? 'get'),
				'protocol' => $_SERVER["SERVER_PROTOCOL"] ?? 'HTTP/1.1',
				'uri' => $_SERVER['REQUEST_URI'] ?? '/',
				'params' => null,
			];
		container("#in.params", $result['params']);
		container("#in.input", $result);
		return $result;
	};
	static $getHeaders = function(){
		$headers = null;
		if(function_exists('getallheaders')) {
			$_headers = getallheaders();
			foreach($_headers as $name => $value) $headers[strtolower($name)] = $value;
		}else{
			foreach($_SERVER as $n => $v){
				if(str_starts_with($n, 'HTTP_')) $headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($n, 5))))] = $v;
			}
		}
		container("#in.headers", $headers);
		return $headers;
	};
	static $getBody = function(){
		$content_type = from('content-type', 'header');
		$content_type = $content_type ? strtolower(trim(explode(';', $content_type)[0])) : null;
		$raw = container("#in.raw") ?? file_get_contents('php://input');
		$parsers = [
			'multipart/form-data' => fn($raw) => $_POST,
			'application/x-www-form-urlencoded' => fn($raw) => (parse_str($raw, $p) ?: $p),
			'application/json' => fn($raw) => json_decode($raw, true),
			...(container('#in.content') ?? []),
		];
		$body = ($parsers[$content_type] ?? $parsers['default'] ?? fn() => [])($raw) ?? [];
		$body['RAW'] = $raw;
		container("#in.body", $body);
		return $body;
	};
	$from = match ($source) {
		'query' => $_GET,
		'cookie' => $_COOKIE,
		'file' => $_FILES,
		'params' => container("#in.params") ?? from('params', 'input') ?? [],
		'header' => container("#in.headers") ?? $getHeaders(),
		'input' => container("#in.input") ?? $getInput(),
		'body' => container("#in.body") ?? $getBody(),
		default => [],
	};
	return $name !== null ? ($from[$name] ?? null) : $from;
}