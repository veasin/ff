<?php
declare(strict_types=1);
namespace ff\resource;

use function ff\container;

/**
 * Redis 资源连接池，按配置名管理连接生命周期。
 * ```
 * $redis = redis('default');   // 获取/创建默认连接
 * $redis = redis('cache');     // 获取/创建指定配置连接（修复多配置 bug）
 * redis(null);                  // 清空所有连接和失败标记
 * ```
 * 配置通过 container("#redis.{name}") 读取，name 默认 'default'。
 * 如需 Swoole 协程版 Redis，通过工厂注入：container('#resource/redis:', fn($config) => ...)
 * @param string|null $name 配置名，null 清空全部
 * @return \Redis|null
 */
function redis(?string $name = 'default'): ?\Redis{
	static $pool = [];
	static $failed = [];
	static $lastUsed = [];
	static $ttl = null;
	static $hasRedis = null;
	if($name === null){
		$pool = [];
		$failed = [];
		$lastUsed = [];
		return null;
	}
	$hasRedis ??= class_exists('\Redis');
	if(!$hasRedis) return null;
	$ttl ??= container('#resource.redis.ttl') ?? 0;
	if(isset($failed[$name]) && time() - $failed[$name] < 1) return null;
	if(isset($pool[$name])){
		if($ttl <= 0 || time() - ($lastUsed[$name] ?? 0) < $ttl) return $pool[$name];
		try{
			$pool[$name]->ping();
			$lastUsed[$name] = time();
			return $pool[$name];
		}catch(\Exception $e){
			unset($pool[$name]);
		}
	}
	$config = container("#redis.{$name}") ?? [];
	if(empty($config)) $config = ['host' => '127.0.0.1', 'port' => 6379];
	try{
		$factory = container('#resource/redis:');
		if($factory){
			$pool[$name] = $factory($config);
		}
		else{
			$c = new \Redis();
			$c->connect($config['host'], $config['port'] ?? 6379, $config['timeout'] ?? 0.0);
			if(isset($config['password'])) $c->auth($config['password']);
			if(isset($config['database'])) $c->select((int)$config['database']);
			$pool[$name] = $c;
		}
		$lastUsed[$name] = time();
		unset($failed[$name]);
		return $pool[$name];
	}catch(\Exception $e){
		$failed[$name] = time();
		return null;
	}
}
