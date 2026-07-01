<?php
declare(strict_types=1);
namespace ff\queue;

use function ff\container;

/**
 * 数据库队列驱动（基于 PDO，零新依赖）。
 * 使用 SQLite/MySQL 表实现可靠队列，支持 ack/nack/retry。
 * 生产: INSERT
 * 消费: SELECT + DELETE（类 BRPOP 语义），失败时 re-INSERT
 * ```
 * // 通常在 queue() 中自动调用，也可直接使用：
 * db('orders', ['id' => 1]);                                 // 生产
 * db('orders', fn($m) => true);                              // 消费
 * db('orders', fn($m) => true, ['config' => 'queue']);       // 指定 db 连接
 * ```
 * 配置: container('#queue/db') — map {table, config, db_type}
 * 表结构自动创建（SQLite 兼容）:
 *   ff_queue(id, queue, body, available_at, created_at)
 * @param string     $queue   队列名
 * @param mixed      $message 消息（Closure=消费模式）
 * @param array|null $config  配置（table/config/db_type），内联覆盖容器配置
 * @return mixed
 */
function db(string $queue, mixed $message, ?array $config = null): mixed{
	static $created = [];
	$defaults = container('#queue/db') ?? [];
	$config = array_merge($defaults, $config ?? []);
	$table = $config['table'] ?? 'ff_queue';
	$cfg = $config['config'] ?? null;
	$isMySQL = ($config['db_type'] ?? 'sqlite') === 'mysql';
	if(!isset($created[$table])){
		$created[$table] = true;
		\ff\db($isMySQL
			? "CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY, queue VARCHAR(255) NOT NULL, body TEXT NOT NULL, available_at INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0) ENGINE=InnoDB"
			: "CREATE TABLE IF NOT EXISTS \"{$table}\" (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT NOT NULL, body TEXT NOT NULL, available_at INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL DEFAULT 0)", configName: $cfg);
	}
	if($message instanceof \Closure){
		$now = time();
		$row = \ff\db("SELECT id, body FROM \"{$table}\" WHERE queue = ? AND available_at <= ? ORDER BY id ASC LIMIT 1", [$queue, $now], 'row', $cfg);
		if(!$row) return false;
		\ff\db("DELETE FROM \"{$table}\" WHERE id = ?", [$row['id']], 'ok', $cfg);
		$msg = @unserialize($row['body']);
		if($msg === false && $row['body'] !== 'b:0;') return null;
		$ret = $message($msg);
		if($ret === true) ;
		elseif($ret === false) \ff\db("INSERT INTO \"{$table}\" (queue, body, available_at, created_at) VALUES (?, ?, 0, ?)", [$queue, $row['body'], $now], 'ok', $cfg);
		elseif($ret === null) ;
		elseif(is_int($ret) && $ret > 0) \ff\db("INSERT INTO \"{$table}\" (queue, body, available_at, created_at) VALUES (?, ?, ?, ?)", [$queue, $row['body'], $now + $ret, $now], 'ok', $cfg);
		return true;
	}
	return \ff\db("INSERT INTO \"{$table}\" (queue, body, created_at) VALUES (?, ?, ?)", [$queue, serialize($message), time()], 'ok', $cfg);
}
