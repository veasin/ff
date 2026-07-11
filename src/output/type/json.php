<?php
declare(strict_types=1);
namespace ff\output\type;
/**
 * @param mixed $data
 * @param array $meta
 * @return array 返回 [$data, $meta]
 * @internal
 */
function json(mixed $data, array $meta): array{
	if($data === null) return [null, $meta];
	$options = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	if($meta['pretty'] ?? false) $options |= JSON_PRETTY_PRINT;
	try{
		$data = json_encode($data, $options);
		$meta['headers'] = [...($meta['headers'] ?? []), 'Content-Type' => 'application/json; charset=UTF-8'];
	}catch(\JsonException $e){
		$meta['code'] = 500;
		$meta['message'] = $e->getMessage();
		$data = null;
	}
	return [$data, $meta];
}
