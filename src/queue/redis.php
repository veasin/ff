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
	$defaults = container('#queue/redis') ?? [];
	$config = array_merge($defaults, $config ?? []);
	$name = $config['config'] ?? 'default';
	$conn = \ff\resource\redis($name);
	if(!$conn) return null;
	$prefix = $config['prefix'] ?? '';
	if($message instanceof \Closure){
		$timeout = $config['timeout'] ?? 0;
		$result = $conn->brPop($prefix . $queue, $timeout);
		if(!$result) return false;
		[$q, $raw] = $result;
		$msg = unserialize($raw);
		$ret = $message($msg);
		if($ret === true);
		elseif($ret === false) $conn->lPush($prefix . $queue, $raw);
		elseif($ret === null);
		elseif(is_int($ret) && $ret > 0){ sleep($ret); $conn->lPush($prefix . $queue, $raw); }
		return true;
	}
	return $conn->lPush($prefix . $queue, serialize($message));
}
