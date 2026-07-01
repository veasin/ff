<?php
declare(strict_types=1);
namespace ff\cache;

use function ff\container;

/**
 * Redis 缓存驱动
 * 使用方式（CRUD）:
 * ```
 * redis();                                            // 触发，返回 null
 * redis('key');                                       // 单键读取
 * redis(null);                                        // 清空全部
 * redis(['k1', 'k2']);                                // 批量读取
 * redis(['k1' => 'v1']);                              // 批量写入
 * redis('key', 'val');                                // 单键写入（无 TTL）
 * redis('key', null);                                 // 单键删除
 * redis('key', 'val', 60);                            // 写入带 TTL（int 简写）
 * redis('key', 'val', ['ttl' => 60, 'config' => 'cfg']);  // 写入带 TTL+配置名
 * redis('key', 'val', 'cfg');                         // 写入+配置名
 * ```
 * 使用方式（工厂）:
 * ```
 * redis('key', middleware: true);                     // 中间件/默认 TTL
 * redis('key', middleware: 60);                       // 中间件/TTL 简写
 * redis('key', middleware: ['ttl' => 60]);            // 中间件/配置数组
 * redis('key', middleware: 'cfg');                    // 中间件/配置名
 * redis('key', 'fallback', middleware: true);         // 中间件/$value 兜底
 * ```
 * @param string|array|null $key 缓存键，null 清空，array 批量操作
 * @param mixed $value 缓存值或 null 表示删除；工厂模式下作为 $next() 返回 null 时的兜底
 * @param string|int|array $set int→['ttl'=>int], string→['config'=>string], array 原样；含 config 时从容器读取并合并
 * @param bool|int|string|array $middleware false=CRUD 模式；非 false 时返回中间件闭包；int→TTL, string→config, array→配置
 * @return mixed CRUD 模式返回查询结果/写入结果/null；工厂模式返回 callable
 */
function redis(string|array|null $key = null, mixed $value = null, string|int|array $set = [], bool|int|string|array $middleware = false): mixed{
	if(!is_bool($middleware)) [$middleware, $set] = [true, $middleware];
	if(is_int($set)) $set = ['ttl' => $set];
	if(is_string($set)) $set = ['config' => $set];
	if(isset($set['config'])) $set = [...(container("#redis.{$set['config']}") ?? []), ...$set];
	if($middleware) return function($next) use ($key, $set, $value){
		if(!is_string($key)) return $next();
		$prefix = $set['prefix'] ?? '';
		$k = $prefix . $key;
		$v = redis($k);
		if(null !== $v) return $v;
		$v = $next() ?? $value;
		if(null !== $v) redis($k, $v, $set);
		return $v;
	};
	$configName = $set['config'] ?? 'default';
	$conn = \ff\resource\redis($configName);
	if(!$conn) return null;
	$prefix = $set['prefix'] ?? '';
	return match (func_num_args()) {
		0 => null,
		1 => match (true) {
			null === $key => $conn->flushAll(),
			is_string($key) => ($v = $conn->get($prefix . $key)) !== false ? unserialize($v) : null,
			array_is_list($key) => array_combine($key, array_map(fn($k) => ($v = $conn->get($prefix . $k)) !== false ? unserialize($v) : null, $key)),
			default => (array_walk($key, fn($v, $k) => $conn->set($prefix . $k, serialize($v))) ? null : null),
		},
		2 => null === $value ? $conn->del($prefix . $key) : $conn->set($prefix . $key, serialize($value)),
		default => $conn->setex($prefix . $key, $set['ttl'] ?? 0, serialize($value)),
	};
}
