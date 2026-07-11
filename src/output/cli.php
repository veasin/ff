<?php
declare(strict_types=1);
namespace ff\output;
/**
 * @param mixed $content
 * @param array $meta
 * @return void
 * @internal
 */
function cli(mixed $content, array $meta): void{
	if($content !== null) echo is_string($content) ? $content : '';
	$code = $meta['code'] ?? 200;
	exit(match(true){
		$code < 400 => 0,
		$code < 500 => 1,
		default => 2,
	});
}
