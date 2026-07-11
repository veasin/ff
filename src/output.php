<?php
declare(strict_types=1);
namespace ff;

use ff\output\status;

/**
 * 输出数据，支持多种格式和环境。
 * 调用方式：
 * - 0 参：output()                             手动触发发送已有数据
 * - 1 参：output($data)                        输出数据（默认 JSON 格式）
 * - 1 参：output(Status::NotFound)             使用 Status enum 状态码
 * - 2 参：output($data, $set)                  输出数据+元配置
 * $set 类型：
 * - int  ：作为状态码
 * - string：作为视图模板文件路径（等价于 ['file' => $path, 'type' => 'view']）
 * - array ：完整元配置，支持以下键：
 * | 键        | 类型      | 默认值      | 说明                                      |
 * |-----------|-----------|-------------|-------------------------------------------|
 * | code      | int       | 200         | 状态码（HTTP → 状态码，CLI → exit code）     |
 * | type      | string    | 'json'      | 输出格式，内置 'json'/'view'/'file'，或自定义 |
 * | file      | string    | -           | 文件路径：type=view 时作模板，type=file 时作下载 |
 * | headers   | array     | []          | 元数据键值对（HTTP 中作响应头）              |
 * | message   | string    | ''          | 状态描述                                    |
 * | pretty    | bool      | false       | JSON 是否美化输出                           |
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
 * @param array|int|string|null $set  元配置
 * @return null
 */
function output(mixed $data = null, array|int|string|null $set = null): null{
	if(0 === func_num_args()){
		$tx = container('#out.op');
		if(!$tx) return null;
		[$data, $meta] = $tx;
		ext('out.emit', container('#out.emit') ?? 'http', ...ext('out.type', $meta['type'] ?? container('#out.type') ?? 'json', $data, $meta));
		return container('#out.op', null);
	}
	if($data instanceof status){
		$meta = is_array($set) ? $set : [];
		$meta['code'] = $data->value;
		$data = null;
	}
	else{
		$meta = match (true) {
			is_int($set) => ['code' => $set],
			is_string($set) => ['file' => $set, 'type' => 'view'],
			is_array($set) => $set,
			default => [],
		};
		$meta['code'] ??= 200;
	}
	return container('#out.op', [$data, $meta]);
}
