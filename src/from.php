<?php
declare(strict_types=1);
namespace ff;
/**
 * 从指定来源获取原始值。input() 内部调用此函数，也可独立使用获取未经验证的原始数据。
 * 来源说明：
 * - query: $_GET 参数
 * - cookie: $_COOKIE
 * - file: $_FILES
 * - params: 路由参数（可通过 container('#in.params') 预置）
 * - header: 请求头（自动统一小写键名）
 * - input: 请求元信息（method, protocol, uri, params）
 * - body: 请求体（自动按 Content-Type 解析 JSON/form/ multipart）
 * ```
 * $id = from('id', 'query');                                        // 从 Query 获取
 * $name = from('name', 'body');                                     // 从 Body 获取
 * $token = from('authorization', 'header');                         // 从 Header 获取
 * $allHeaders = from(null, 'header');                               // 获取整个来源
 * $values = from(['id', 'name'], 'query');                          // 批量获取（list）
 * $data = from('id', ['id' => 123, 'name' => 'test']);             // 直接使用数组
 * $data = from(['id' => 0, 'name' => '?'], 'query');               // map：按 key 读取，null 时用默认值兜底
 * container('#in.params', ['id' => 123]);                           // 预置路由参数
 * container('#in.content', ['application/xml' => fn($r) => ...]);  // 扩展 content-type 解析
 * ```
 * @param string|null|array $name   键名；null 返回整个来源；list 数组批量读取；map 数组批量读取+默认值兜底
 * @param string|array      $source 来源名称（query|cookie|file|params|header|input|body），或直接提供数组作为来源
 * @return mixed 来源中指定键的值；整个来源；批量读取时返回关联数组；不存在返回 null
 */
function from(string|null|array $name, string|array $source = 'body'): mixed{
	static $getInput = function(){
		$result = container('#mode:cli')
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
		if(function_exists('getallheaders')){
			foreach(getallheaders() as $name => $value){
				$name = strtolower($name);
				foreach((array)$value as $v){
					$headers[$name] = isset($headers[$name]) ? [...(array)$headers[$name], $v] : $v;
				}
			}
		}
		else{
			foreach($_SERVER as $n => $v){
				if(str_starts_with($n, 'HTTP_')){
					$name = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($n, 5))));
					$headers[$name] = isset($headers[$name]) ? [...(array)$headers[$name], $v] : $v;
				}
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
			'application/x-www-form-urlencoded' => fn($raw) => (parse_str($raw, $p) ?? $p),
			'application/json' => fn($raw) => json_decode($raw, true),
			...(container('#in.content') ?? []),
		];
		$body = ($parsers[$content_type] ?? $parsers['default'] ?? fn() => [])($raw) ?? [];
		$body['RAW'] = $raw;
		container("#in.body", $body);
		return $body;
	};
	$from = is_array($source)
		? $source
		: match ($source) {
			'query' => $_GET,
			'cookie' => $_COOKIE,
			'file' => $_FILES,
			'params' => container("#in.params") ?? from('params', 'input') ?? [],
			'header' => container("#in.headers") ?? $getHeaders(),
			'input' => container("#in.input") ?? $getInput(),
			'body' => container("#in.body") ?? $getBody(),
			default => [],
		};
	return match (true) {
		$name === null => $from,
		is_array($name) => array_is_list($name) ? array_map(fn($k) => $from[$k] ?? null, array_combine($name, $name)) : array_merge($name, array_intersect_key($from, $name)),
		default => $from[$name] ?? null,
	};
}