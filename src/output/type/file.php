<?php
declare(strict_types=1);
namespace ff\output\type;
/**
 * @param mixed $data
 * @param array $meta
 * @return array 返回 [$data, $meta]
 * @internal
 */
function file(mixed $data, array $meta): array{
	$path = $meta['file'] ?? null;
	if(!$path || !file_exists($path)){
		$meta['code'] = 404;
		return [null, $meta];
	}
	$download = $data;
	$data = file_get_contents($path);
	$mime = mime_content_type($path);
	if($mime) $meta['headers'] = [...($meta['headers'] ?? []), 'Content-Type' => $mime];
	if($download) $meta['headers'] = [
		...($meta['headers'] ?? []),
		'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
		'Content-Length' => filesize($path),
	];
	return [$data, $meta];
}
