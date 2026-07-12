<?php
declare(strict_types=1);
namespace ff;
/**
 * 从指定来源获取原始值。input() 内部调用此函数，也可独立使用获取未经验证的原始数据。
 * 内置来源：query|cookie|file|params|input，通过 ext 注册的来源：body|header，可扩展自定义来源。
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
 * container('^#ext.from.session', fn() => $_SESSION);              // 扩展自定义来源
 * ```
 * @param string|null|array $name   键名；null 返回整个来源；list 数组批量读取；map 数组批量读取+默认值兜底
 * @param string|array      $source 来源名称（query|cookie|file|params|input|header|body|...），或直接提供数组作为来源
 * @return mixed 来源中指定键的值；整个来源；批量读取时返回关联数组；不存在返回 null
 */
function from(string|null|array $name, string|array $source = 'body'): mixed{
	static $getInput = function(){
		$result = container('#mode:cli')
			? [
				'method' => 'cli',
				'protocol' => null,
				'uri' => implode(' ', $_SERVER['argv']),
			]
			: [
				'method' => strtolower($_SERVER['REQUEST_METHOD'] ?? 'get'),
				'protocol' => $_SERVER["SERVER_PROTOCOL"] ?? 'HTTP/1.1',
				'uri' => $_SERVER['REQUEST_URI'] ?? '/',
			];
		container("#in.input", $result);
		return $result;
	};
	static $getParams = function(){
		$params = container('#mode:cli') ? (args(array_slice($_SERVER['argv'], 1)) ?? []) : [];
		container("#in.params", $params);
		return $params;
	};
	$from = is_array($source)
		? $source
		: match ($source) {
			'query' => $_GET,
			'cookie' => $_COOKIE,
			'file' => $_FILES,
			'params' => container("#in.params") ?? $getParams(),
			'input' => container("#in.input") ?? $getInput(),
			default => ext('from', $source) ?? [],
		};
	return match (true) {
		$name === null => $from,
		is_array($name) => array_is_list($name) ? array_map(fn($k) => $from[$k] ?? null, array_combine($name, $name)) : array_merge($name, array_intersect_key($from, $name)),
		default => $from[$name] ?? null,
	};
}