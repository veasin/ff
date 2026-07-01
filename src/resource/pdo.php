<?php
declare(strict_types=1);
namespace ff\resource;

use function ff\container;

/**
 * PDO 资源连接池，按配置名管理连接生命周期。
 * ```
 * $pdo = pdo('default');   // 获取/创建默认连接
 * $pdo = pdo('slave');     // 获取/创建指定配置连接
 * pdo(null);                // 清空所有连接和失败标记
 * ```
 * 配置通过 container("#db.{name}") 读取，name 默认 'default'。
 * 支持惰性验证（通过 container('#resource.pdo.ttl') 控制验证间隔，默认 0 不验证）。
 * Swoole 环境：可通过 container('#resource/pdo:', fn($config) => ...) 注入自定义工厂。
 * @param string|null $name 配置名，null 清空全部
 * @return \PDO|null
 */
function pdo(?string $name = 'default'): ?\PDO{
	static $pool = [];
	static $failed = [];
	static $lastUsed = [];
	static $ttl = null;
	if($name === null){
		$pool = [];
		$failed = [];
		$lastUsed = [];
		return null;
	}
	$ttl ??= container('#resource.pdo.ttl') ?? 0;
	if(isset($failed[$name]) && time() - $failed[$name] < 1) return null;
	if(isset($pool[$name])){
		if($ttl <= 0 || time() - ($lastUsed[$name] ?? 0) < $ttl) return $pool[$name];
		try{
			$pool[$name]->query('SELECT 1');
			$lastUsed[$name] = time();
			return $pool[$name];
		}catch(\PDOException $e){
			unset($pool[$name]);
		}
	}
	$config = container("#db.{$name}") ?? null;
	if(!is_array($config) || !isset($config['dsn'])) return null;
	try{
		$factory = container('#resource/pdo:');
		$pool[$name] = $factory
			? $factory($config)
			: new \PDO($config['dsn'], $config['username'] ?? null, $config['password'] ?? null, ($config['options'] ?? []) + [
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
					\PDO::ATTR_EMULATE_PREPARES => false,
				]);
		$lastUsed[$name] = time();
		unset($failed[$name]);
		return $pool[$name];
	}catch(\PDOException $e){
		$failed[$name] = time();
		return null;
	}
}
