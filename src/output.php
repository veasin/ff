<?php
namespace nx;
use function nx\output\{file, http, view, json};
/**
 * 输出数据，支持多种格式和模板
 *  output(data, int statusCode, responseSet)
 *  output(data, string format, responseSet):void
 *  output(data, string format, file|responseSet)->string&header
 *  output(data, responseSet)
 *  output(responseSet)
 *  output()                     // 立即触发发送（用于 worker 模式）
 * @param mixed|null        $data        要输出的数据
 * @param int|string|null   $mode        格式名或状态码
 * @param array|string|null $responseSet 响应设置或模板文件
 * @return null
 */
function output(mixed $data = null, int|string|null $mode = 200, array|string|null $responseSet = []): null{
	static $render =function(){
		$response = container('#out.response');
		$formats = [
			'json' => json(...),
			'view' => view(...),
			'file' => file(...),
			'http' => http(...),
			...(container('#out.formats') ?? []),
		];
		return $formats[$response['format'] ?? 'json']($response, $formats);
	};
	if(0 === func_num_args()){
		if(!container('#out.response')) container('#out.response', [...$responseSet, 'code' => 404, 'format' => 'http']);
		if(!container('#out.render')) container('#out.render', $render);
		return container('#out.render*');
	}
	$format = is_string($mode) ? $mode : null;
	if('view' === $format || 'file' === $format){
		if(is_string($responseSet)) [$file, $responseSet] = [$responseSet, []];
		else $file = $responseSet['file'] ?? null;
	}
	return container('#out.response', [...$responseSet, 'body' => $data, 'code' => is_numeric($mode) ? $mode : 200, 'format' => $format, 'file' => $file ?? null]);
}
