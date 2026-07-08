<?php
declare(strict_types=1);
namespace ff;
/**
 * 扩展驱动调用函数，五种模式：
 * ```
 * ext('queue', 'db', $msg)                 // 1. 单驱
 * ext('log', true, $level, $msg)           // 2. 广播
 * ext('log', false, $level, $msg)          // 3. 遍历
 * ext('http', null, $method, $url)         // 4. 尝试
 * ext('http', ['stream','curl'], $url)     // 5. 指定序
 * ```
 * @param string                 $domain 扩展点名称（函数名）
 * @param string|bool|array|null $name   驱动标识/模式
 * @param mixed                  ...$args
 * @return mixed
 */
function ext(string $domain, string|bool|array|null $name, mixed ...$args): mixed{
	$entry = container("^#ext.$domain") ?? [];
	if(empty($entry)) return null;
	elseif(is_string($name)){
		$h = $entry[$name] ?? null;
		return is_callable($h) ? $h(...$args) : null;
	}
	elseif(true === $name){
		$r = [];
		foreach($entry as $n => $h) is_callable($h) && $r[$n] = $h(...$args);
		return $r;
	}
	elseif(false === $name){
		foreach($entry as $h) is_callable($h) && $h(...$args);
	}
	elseif(null === $name){
		foreach($entry as $h){
			if(!is_callable($h)) continue;
			$r = $h(...$args);
			if($r !== null) return $r;
		}
	}
	elseif(is_array($name)){
		foreach($name as $n){
			$h = $entry[$n] ?? null;
			if(!is_callable($h)) continue;
			$r = $h(...$args);
			if($r !== null) return $r;
		}
	}
	return null;
}
