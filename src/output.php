<?php
declare(strict_types=1);
namespace ff;

use ff\output\status;
use function ff\output\http;

/**
 * 输出数据，支持多种格式和模板。
 *
 * 调用方式：
 * - 0 参：output()                             手动触发发送已有响应
 * - 1 参：output($data)                        输出数据（默认 JSON 格式）
 * - 1 参：output(Status::NotFound)             使用 Status enum 状态码
 * - 2 参：output($data, $set)                  输出数据+配置
 *
 * $set 类型：
 * - int  ：作为 HTTP 状态码
 * - string：作为视图模板文件路径（等价于 ['file' => $path, 'type' => 'view']）
 * - array ：完整配置，支持以下键：
 *
 * | 键         | 类型      | 默认值      | 说明                                      |
 * |------------|-----------|-------------|-------------------------------------------|
 * | code       | int       | 200         | HTTP 状态码                                |
 * | type       | string    | 'json'      | 输出格式，内置 'json'/'view'/'file'，或自定义 |
 * | file       | string    | -           | 文件路径：type=view 时作模板，type=file 时作下载 |
 * | headers    | array     | []          | HTTP 响应头键值对                           |
 * | message    | string    | ''          | HTTP 状态消息                               |
 * | pretty     | bool      | false       | JSON 是否美化输出                           |
 *
 * 示例：
 * ```
 * output(['status' => 'ok']);                              // JSON 输出（默认格式）
 * output(Status::NotFound);                                // Status enum → 404
 * output($data, 201);                                      // 数据+状态码
 * output($data, ['type' => 'json']);                       // 指定 JSON 格式
 * output($data, ['code' => 201, 'headers' => ['X-Custom' => 'v']]);  // 状态码+自定义头
 * output($data, ['file' => 'template.php', 'code' => 201]);// 视图模板+状态码
 * output($data, 'template.php');                           // 视图模板（简写）
 * output($data, ['type' => 'file', 'file' => '/path/to/photo.jpg']);   // 输出文件
 * output(true,  ['type' => 'file', 'file' => '/path/to/photo.jpg']);   // 下载文件
 * output();                                                // 手动触发发送
 * ```
 * @param mixed                 $data 要输出的数据或 Status enum
 * @param array{code?: int, type?: string, file?: string, headers?: array<string, string>, message?: string, pretty?: bool}|int|string|null $set 配置
 * @return null
 */
function output(mixed $data = null, array|int|string|null $set = null): null{
	static $render = function(){
		$response = container('#out.response');
		$type = $response['type'] ?? container('#out.default') ?? 'json';
		$response = ext('output', $type, $response) ?? $response;
		$emit = container('#out.emit') ?? (container('#mode:cli') ? function($r){
			echo $r['body'] ?? '';
			$code = $r['code'] ?? 200;
			exit(match (true) {
				$code < 400 => 0,
				$code < 500 => 1,
				default => 2,
			});
		} : http(...));
		return $emit($response);
	};
	if(0 === func_num_args()){
		if(!container('#out.response')) container('#out.response', ['code' => 404, 'type' => 'http']);
		if(!container('#out.render')) container('#out.render', $render);
		$r = container('#out.render*');
		container('#out.render', fn() => null);
		return $r;
	}
	static $shutdown = null;
	if(!$shutdown && !container('#mode:worker')){
		$shutdown = true;
		register_shutdown_function(fn() => output());
	}
	if($data instanceof status){
		$set = is_array($set) ? $set : [];
		$set['code'] = $data->value;
		$data = null;
	}
	$config = match (true) {
		is_int($set) => ['code' => $set],
		is_string($set) => ['file' => $set, 'type' => 'view'],
		is_array($set) => $set,
		default => [],
	};
	$config['code'] ??= 200;
	return container('#out.response', ['body' => $data, ...$config]);
}
