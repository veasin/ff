<?php
declare(strict_types=1);
namespace ff;
/**
 * 从指定来源获取原始值。input() 内部调用此函数，也可独立使用获取未经验证的原始数据。
 * 内置来源：query|cookie|file，通过 ext 注册的来源：body|header|input|params，可扩展自定义来源。
 * 支持 ext 扩展：domain='from'，handler 签名 fn(): array，具体参数与返回值见 doc/functions.md。
 * ```
 * $id = from('id', 'query');                                        // 从 Query 获取
 * $name = from('name', 'body');                                     // 从 Body 获取
 * $token = from('authorization', 'header');                         // 从 Header 获取
 * $allHeaders = from(null, 'header');                               // 获取整个来源
 * $values = from(['id', 'name'], 'query');                          // 批量获取（list）
 * $data = from('id', ['id' => 123, 'name' => 'test']);             // 直接使用数组
 * $data = from(['id' => 0, 'name' => '?'], 'query');               // map：按 key 读取，null 时用默认值兜底
 * container('#in.params', ['id' => 123]);                           // 预置路由参数
 * container('^#ext.body.text/xml', fn($raw) => ...);               // 扩展 content-type 解析
 * container('^#ext.from.session', fn() => $_SESSION);              // 扩展自定义来源
 * ```
 * @param string|null|array $name   键名；null 返回整个来源；list 数组批量读取；map 数组批量读取+默认值兜底
 * @param string|array      $source 来源名称（query|cookie|file|params|input|header|body|...），或直接提供数组作为来源
 * @return mixed 来源中指定键的值；整个来源；批量读取时返回关联数组；不存在返回 null
 */
function from(string|null|array $name, string|array $source = 'body'): mixed{
	$from = is_array($source)
		? $source
		: match ($source) {
			'query' => $_GET,
			'cookie' => $_COOKIE,
			'file' => $_FILES,
			default => container("#in.$source") ?? ext('from', $source) ?? [],
		};
	return match (true) {
		$name === null => $from,
		is_array($name) => array_is_list($name) ? array_map(fn($k) => $from[$k] ?? null, array_combine($name, $name)) : array_merge($name, array_intersect_key($from, $name)),
		default => $from[$name] ?? null,
	};
}
