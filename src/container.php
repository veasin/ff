<?php
declare(strict_types=1);
namespace ff;
/**
 * 容器方法，支持双生命周期（持久/请求级）与延迟构建
 * 基本操作：
 * ```
 * container(null);                             // 清空请求级
 * container(null, '^');                        // 清空持久级
 * $host = container('database.host');          // 读取值（支持 . 分隔，请求级+持久级深度合并）
 * $host = container('^database.host');         // 仅读取持久级
 * container('database.host', 'localhost');     // 设置值（写入请求级）
 * container('^database.host', 'localhost');    // 设置值（写入持久级）
 * container('database.host', null);            // 删除键（设置 null）
 * $values = container(['database.host', 'app.debug']);// 批量读取
 * container(['k' => 'v']);                     // 批量设置（写入请求级）
 * container(['k' => 'v'], '^');                // 批量持久设置
 * container(['^persist.k' => 'v', 'request.k' => 'v']);// 数组 key 可单独加 ^ 修饰符覆盖
 * container('db', fn() => new PDO(...));       // 闭包工厂：存入闭包，用 * 后缀执行
 * $closure = container('db');                  // 返回闭包本身
 * $pdo     = container('db*');                 // 执行闭包返回结果
 * container('plain', 'str');                   // 非闭包值忽略 *
 * container('plain*');                         // 返回 'str'
 * ```
 * 深度合并：请求级与持久级的关联数组自动递归合并，非 list 数组直接替换。
 * @param array|string|null $key   键名，支持 . 分隔访问嵌套数组，^ 前缀持久，* 后缀执行闭包
 * @param mixed|null        $value 值，null 删除键，true/'^' 表示持久操作
 * @return mixed 读取时返回值，设置时返回 void
 */
function container(array|string|null $key = null, mixed $value = null): mixed{
	static $core = ['#mode:cli' => PHP_SAPI === 'cli'], $persist = [], $request = [];
	static $get = fn(array $arr, string $k) => array_reduce(explode('.', $k), fn($c, $p) => is_array($c) ? ($c[$p] ?? null) : null, $arr);
	static $set = function(array &$arr, string $k, mixed $v): void{
		$parts = explode('.', $k);
		$last = array_pop($parts);
		$cur = &$arr;
		foreach($parts as $p){
			if(!isset($cur[$p]) || !is_array($cur[$p])) $cur[$p] = [];
			$cur = &$cur[$p];
		}
		if($v === null) unset($cur[$last]);
		else $cur[$last] = $v;
	};
	static $parseKey = function($key){
		if($key === '') throw new \InvalidArgumentException('Key cannot be empty');
		$persist = $key[0] === '^';
		$execute = $key[-1] === '*';
		return [substr($key, $persist ? 1 : 0, $execute ? -1 : null), $persist, $execute];
	};
	static $merge = null;
	$merge = function(array $base, array $override) use (&$merge): array{
		if(array_is_list($base)) return $override;
		foreach($override as $k => $v){
			if(is_string($k) && is_array($v) && is_array($base[$k] ?? null)){
				if(!array_is_list($base[$k]) && !array_is_list($v)) $base[$k] = $merge($base[$k], $v);
				else $base[$k] = $v;
			}
			else $base[$k] = $v;
		}
		return $base;
	};
	static $read = function(string $key) use ($get, $parseKey, $merge, &$core, &$persist, &$request){
		[$k, $fromPersist, $execute] = $parseKey($key);
		if($fromPersist){
			$val = $get($persist, $k) ?? $get($core, $k);
		}else{
			$reqVal = $get($request, $k);
			if($reqVal === null) $val = $get($persist, $k) ?? $get($core, $k);
			elseif(!is_array($reqVal) || array_is_list($reqVal)) $val = $reqVal;
			else{
				$perVal = $get($persist, $k);
				$val = is_array($perVal) ? $merge($perVal, $reqVal) : $reqVal;
			}
		}
		return ($execute && $val instanceof \Closure) ? $val() : $val;
	};
	static $write = function(string $key, mixed $value) use ($parseKey, $set, &$persist, &$request){
		[$k, $toPersist, $execute] = $parseKey($key);
		if($execute) trigger_error("container write with * suffix has no effect: {$key}", E_USER_WARNING);
		$toPersist ? $set($persist, $k, $value) : $set($request, $k, $value);
		return null;
	};
	return match (func_num_args()) {
		1 => match (true) {
			is_string($key) => $read($key),
			$key === null => ($request = []) ?: [],
			is_array($key) && array_is_list($key) => array_map(fn($k) => container($k), $key),
			is_array($key) => (array_walk($key, fn($v, $k) => container($k, $v)) ? null : null),
			default => null,
		},
		2 => match (true) {
			is_string($key) => $write($key, $value),
			$key === null && ($value === true || $value === '^') => ($persist = []) ?: [],
			is_array($key) => (array_walk($key, fn($v, $k) => container((($value === true || $value === '^') && !str_starts_with($k, '^')) ? '^' . $k : $k, $v)) ? null : null),
			default => null,
		},
		default => null,
	};
}