<?php
declare(strict_types=1);
namespace nx;
/**
 * 容器方法，支持双生命周期（持久/请求级）与延迟构建
 * 基本操作：
 * ```
 * $all = container();                          // 获取所有配置
 * container(null);                             // 清空请求级
 * container(null, '^');                        // 清空持久级
 * $host = container('database.host');          // 读取值（支持 . 分隔，先查请求级再查持久级）
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
 * @param array|string|null $key   键名，支持 . 分隔访问嵌套数组，^ 前缀持久，* 后缀执行闭包
 * @param mixed|null        $value 值，null 删除键，true/'^' 表示持久操作
 * @return mixed 读取时返回值，设置时返回 void
 */
function container(array|string|null $key = null, mixed $value = null): mixed{
	static $core = [
		'#mode:cli' => PHP_SAPI === 'cli',
		'i18n' => [
			'lang' => 'zh_CN',
			'zh_CN' => [
				'#error:internal' => '服务器内部错误',
				'#error:rate_limit' => '请求过于频繁，请稍后再试',
				'#error:csrf_mismatch' => 'CSRF 令牌不匹配',
				'#auth:apikey_required' => '需要 API Key',
				'#auth:realm_basic' => '需要认证',
				'#auth:realm_token' => '需要 Token 认证',
				'#auth:realm_jwt' => '需要 JWT 认证',
				'#container:key_empty' => '键名不能为空',
				'#container:star_warn' => 'container 写入操作带 * 后缀无效: {key}',
				'#test:passed' => '[g]✔ 全部通过[y]: {passed}/{total}',
				'#test:failed' => '[r]● 测试失败[y]: {count}, [g]{passed}[y]/{total}',
				'#test:case' => "[r]▶ {label}\n\t[n]预期: {expected}\n\t[n]实际: {actual}",
			],
			'en_US' => [
				'#error:internal' => 'Internal server error',
				'#error:rate_limit' => 'Too many requests. Please try again later.',
				'#error:csrf_mismatch' => 'CSRF token mismatch',
				'#auth:apikey_required' => 'API Key required',
				'#auth:realm_basic' => 'Authentication required',
				'#auth:realm_token' => 'Token authentication required',
				'#auth:realm_jwt' => 'JWT authentication required',
				'#container:key_empty' => 'Key cannot be empty',
				'#container:star_warn' => 'container write with * suffix has no effect: {key}',
				'#test:passed' => '[g]✔ All passed[y]: {passed}/{total}',
				'#test:failed' => '[r]● Test failed[y]: {count}, [g]{passed}[y]/{total}',
				'#test:case' => "[r]▶ {label}\n\t[n]Expected: {expected}\n\t[n]Actual: {actual}",
			],
		],
	], $persist = [], $request = [];
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
	return match (func_num_args()) {
		0 => array_merge($persist, $request),
		1 => match (true) {
			is_string($key) => (function() use ($key, $get, $parseKey, &$core, &$persist, &$request){
				[$k, $fromPersist, $execute] = $parseKey($key);
				$val = $fromPersist ? ($get($persist, $k) ?? $get($core, $k)) : ($get($request, $k) ?? $get($persist, $k) ?? $get($core, $k));
				return ($execute && $val instanceof \Closure) ? $val() : $val;
			})(),
			$key === null => ($request = []) ?: [],
			is_array($key) && array_is_list($key) => array_map(fn($k) => container($k), $key),
			is_array($key) => array_walk($key, fn($v, $k) => container($k, $v)) && null,
			default => null,
		},
		2 => match (true) {
			is_string($key) => (function() use ($key, $value, $parseKey, $set, &$persist, &$request){
				[$k, $toPersist, $execute] = $parseKey($key);
				if($execute) trigger_error("container write with * suffix has no effect: {$key}", E_USER_WARNING);
				$toPersist ? $set($persist, $k, $value) : $set($request, $k, $value);
				return null;
			})(),
			$key === null && ($value === true || $value === '^') => ($persist = []) ?: [],
			is_array($key) => array_walk($key, fn($v, $k) => container((($value === true || $value === '^') && !str_starts_with($k, '^')) ? '^' . $k : $k, $v)) && null,
			default => null,
		},
		default => null,
	};
}