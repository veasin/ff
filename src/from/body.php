<?php
declare(strict_types=1);
namespace ff\from;
use function ff\{container, ext, from};
/**
 * 解析并缓存请求体。按 Content-Type 通过 ext('body') 自动解析 JSON/form/multipart。
 * 返回关联数组，含 RAW 键存储原始输入。
 * @return array 请求体关联数组
 * @internal
 */
function body(): array{
	$from = container("#in.body");
	if($from === null){
		$content_type = from('content-type', 'header');
		$content_type = $content_type ? strtolower(trim(explode(';', $content_type)[0])) : null;
		$raw = container("#in.raw") ?? file_get_contents('php://input');
		$body = ext('body', $content_type ?? '', $raw) ?? [];
		$body['RAW'] = $raw;
		$from = $body;
		container("#in.body", $from);
	}
	return $from;
}
