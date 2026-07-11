<?php
declare(strict_types=1);
namespace ff\output;

use function ff\{container, from};

/**
 * @param mixed $content
 * @param array $meta
 * @return void
 * @internal
 */
function http(mixed $content, array $meta): void{
	$status = $meta['code'] ?? ($content !== null ? 200 : 404);
	$message = " $status " . ($meta['message'] ?? '');
	if(!headers_sent()){
		header((from('protocol', 'input') ?? 'HTTP/1.1') . $message);
		header_remove('X-Powered-By');
		$headers = $meta['headers'] ?? [];
		$headers['NX'] = 'V 2005-' . date('Y');
		$is_list = array_is_list($headers);
		foreach($headers as $header => $value){
			if($is_list){
				if(is_array($value)){
					foreach($value as $v){
						header($header . ': ' . $v, false);
					}
				}
				elseif(is_string($value) || $value instanceof \Stringable){
					header($value);
				}
			}
			else header($header . ': ' . $value);
		}
	}
	if($content !== null) echo $content;
}
