<?php
declare(strict_types=1);
namespace ff;

/**
 * 队列操作函数。第二参数为闭包时进入消费模式，否则为生产模式。
 * 未指定驱动时默认尝试 `'default'`，未注册时 ext 返回 null。
 * 支持 ext 扩展：domain='queue'，handler 签名 fn(string $queue, mixed $message, array $config): mixed，具体参数与返回值见 doc/functions.md。
 * 可通过 `#queue` 容器配置指定驱动：
 * ```
 * // ---- 生产 ----
 * queue('orders', ['order_id' => 123]);                    // 推入消息
 * queue('orders', 'plain text');                            // 字符串消息
 * queue('orders', $msg, 'email');                           // 指定命名配置
 * queue('orders', $msg, ['driver' => 'db']);                // 指定内联配置
 * queue(['q1' => 'm1', 'q2' => 'm2']);                     // 批量生产
 *
 * // ---- 消费 ----
 * queue('orders', function($msg) {
 *     return true;        // ack 确认（处理成功）
 *     return false;       // nack + 重新入队
 *     return null;        // nack + 丢弃
 *     return 5;           // 5 秒后重试
 * });
 * ```
 * 配置:
 * - `#queue`          — 默认队列配置（指定 driver 等）
 * - `#queue/db`       — 数据库驱动配置（table/config/db_type）
 * @param string|array|null  $queue   队列名；传入 array 时批量生产
 * @param mixed              $message 消息内容；Closure 时进入消费模式
 * @param string|array|null  $option  命名配置名(string) 或内联配置(array)
 * @return mixed 生产返回 bool（是否成功）；消费返回 true=处理了消息，false=队列空
 */
function queue(string|array|null $queue = null, mixed $message = null, string|array|null $option = null): mixed{
	if(null === $queue) return null;
	if(is_array($queue)){
		$r = [];
		foreach($queue as $k => $v) $r[$k] = queue($k, $v, $option);
		return $r;
	}
	$base = container('#queue') ?? ['driver' => 'default'];
	$config = match (true) {
		is_string($option) => array_merge($base, container("#queue.{$option}") ?? []),
		is_array($option)  => array_merge($base, $option),
		default            => $base,
	};
	return ext('queue', $config['driver'] ?? 'default', $queue, $message, $config);
}
