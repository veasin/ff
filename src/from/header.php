<?php
declare(strict_types=1);
namespace ff\from;
use function ff\{container};
/**
 * 解析并缓存请求头。返回关联数组（键名全小写）。
 * 多值头自动合并为数组。
 * @return array 请求头关联数组
 * @internal
 */
function header(): array{
	$from = container("#in.headers");
	if($from === null){
		$headers = [];
		if(function_exists('getallheaders')){
			foreach(getallheaders() as $name => $value){
				$name = strtolower($name);
				foreach((array)$value as $v){
					$headers[$name] = isset($headers[$name]) ? [...(array)$headers[$name], $v] : $v;
				}
			}
		}
		else{
			foreach($_SERVER as $n => $v){
				if(str_starts_with($n, 'HTTP_')){
					$name = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($n, 5))));
					$headers[$name] = isset($headers[$name]) ? [...(array)$headers[$name], $v] : $v;
				}
			}
		}
		$from = $headers;
		container("#in.headers", $from);
	}
	return $from;
}
