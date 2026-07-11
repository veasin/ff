<?php
declare(strict_types=1);
namespace ff\output\type;
/**
 * @param mixed $data
 * @param array $meta
 * @return array 返回 [$data, $meta]
 * @internal
 */
function view(mixed $data, array $meta): array{
	if(!isset($meta['file']) || !file_exists($meta['file'])) return [$data, $meta];
	ob_start();
	extract(is_array($data) ? $data : []);
	include $meta['file'];
	$data = ob_get_clean();
	$meta['headers'] = [...($meta['headers'] ?? []), 'Content-Type' => 'text/html; charset=UTF-8'];
	return [$data, $meta];
}
