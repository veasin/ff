<?php
declare(strict_types=1);
namespace nx;

use nx\output\status;
use function nx\output\{format\file, format\json, format\view, http};

/**
 * 输出数据，支持多种格式和模板。
 * ```
 * output(['status' => 'ok']);                         // JSON 输出（默认格式）
 * output(['error' => 'not found'], 404);              // 带状态码
 * output(Status::NotFound);                           // Status enum 首参数
 * output($data, 'json');                              // 指定格式
 * output($data, 'view', 'template.php');              // 视图渲染
 * output($data, 'file', '/path/to/file.pdf');         // 文件输出
 * output(true, 'file', '/path/to/file.pdf');          // 文件下载
 * output();                                           // 手动触发发送（worker 模式）
 * ```
 * @param mixed|null        $data        要输出的数据
 * @param int|string|null   $mode        格式名或状态码
 * @param array|string|null $responseSet 响应设置或模板文件
 * @return null
 */
function output(mixed $data = null, int|string|null $mode = 200, array|string|null $responseSet = []): null{
	static $render = function(){
		$response = container('#out.response');
		$type = $response['format'] ?? container('#out.type') ?? 'json';
		$format = container("#out.format.$type") ?? match ($type) {
			'json' => json(...),
			'view' => view(...),
			'file' => file(...),
			default => null,
		};
		$response = $format ? $format($response) : $response;
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
		if(!container('#out.response')) container('#out.response', [...$responseSet, 'code' => 404, 'format' => 'http']);
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
	if($data instanceof status) [$mode, $data] = [$data->value, null];
	$format = is_string($mode) ? $mode : null;
	if('view' === $format || 'file' === $format){
		if(is_string($responseSet)) [$file, $responseSet] = [$responseSet, []];
		else $file = $responseSet['file'] ?? null;
	}
	return container('#out.response', [...$responseSet, 'body' => $data, 'code' => is_numeric($mode) ? $mode : 200, 'format' => $format, 'file' => $file ?? null]);
}
