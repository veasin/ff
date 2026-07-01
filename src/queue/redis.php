<?php
declare(strict_types=1);
namespace ff\queue;

use function ff\container;

/**
 * Redis list 队列驱动。
 * 生产: lPush + serialize
 * 消费: brPop + 返回值驱动 ack/nack/retry
 * ```
 * // 通常在 queue() 中自动调用，也可直接使用：
 * redis('orders', ['id' => 1]);                             // 生产
 * redis('orders', fn($m) => true);                          // 消费
 * redis('orders', fn($m) => true, ['timeout' => 10]);       // 消费指定超时
 * ```
 * 连接: container('#redis.{name}') — name 从 #queue/redis.config 读取，默认 'default'
 * 配置: container('#queue/redis') — {config, prefix, ttl, timeout}
 * @param string     $queue   队列名
 * @param mixed      $message 消息（Closure=消费模式）
 * @param array|null $config  配置（timeout/prefix 等），内联覆盖容器配置
 * @return mixed
 */
function redis(string $queue, mixed $message, ?array $config = null): mixed{
	static $conn = null;
	static $failed = false;
	if($conn === null && !$failed){
		$defaults = container('#queue/redis') ?? [];
		$config = array_merge($defaults, $config ?? []);
		$name = $config['config'] ?? 'default';
		$rc = array_merge(container("#redis.{$name}") ?? [], $config);
		try{
			$c = new \Redis();
			$c->connect($rc['host'] ?? '127.0.0.1', $rc['port'] ?? 6379, $rc['timeout'] ?? 0.0);
			if(isset($rc['password'])) $c->auth($rc['password']);
			if(isset($rc['database'])) $c->select((int)$rc['database']);
			if(isset($rc['prefix'])) $c->setOption(\Redis::OPT_PREFIX, $rc['prefix']);
			$conn = $c;
		}catch(\Exception $e){
			$failed = true;
			return null;
		}
	}
	if($conn === null) return null;
	if($message instanceof \Closure){
		$timeout = $config['timeout'] ?? 0;
		$result = $conn->brPop($queue, $timeout);
		if(!$result) return false;
		[$q, $raw] = $result;
		$msg = unserialize($raw);
		$ret = $message($msg);
		if($ret === true);
		elseif($ret === false) $conn->lPush($queue, $raw);
		elseif($ret === null);
		elseif(is_int($ret) && $ret > 0){ sleep($ret); $conn->lPush($queue, $raw); }
		return true;
	}
	return $conn->lPush($queue, serialize($message));
}
