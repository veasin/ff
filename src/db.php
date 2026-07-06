<?php
declare(strict_types=1);
namespace ff;

use function ff\resource\pdo;

/**
 * 数据库操作函数，支持多种查询模式和事务。
 * ```
 * $user = db('SELECT * FROM users WHERE id = ?', [1], 'row');   // 单行
 * $users = db('SELECT * FROM users', 'list');                    // 列表（无参数时可直接省略绑定位）
 * $count = db('SELECT COUNT(*) FROM users', 'value');            // 单值
 * $id = db('INSERT INTO users (name) VALUES (?)', ['John'], 'id');// 插入返回ID
 * $affected = db('UPDATE users SET name=? WHERE id=?', ['Jane', 1], 'count');// 影响行数
 * $stmt = db('SELECT * FROM users', true);                       // 返回 PDOStatement
 * db('BEGIN'); db('COMMIT'); db('ROLLBACK');                    // 事务
 * ```
 * 参数 $mode 按类型分组：
 * - string: `row` 单行数组, `list` 所有行, `value` 单值, `column` 单列, `pairs` 键值对, `group` 分组, `id` 自增ID, `count` 影响行数, `ok` 成功确认, `exec` 多语句执行
 * - int: 自定义 PDO fetch 模式（如 PDO::FETCH_ASSOC）
 * - true: 返回 PDOStatement
 * - callable: 自定义处理函数 fn($stmt, $pdo) => mixed
 * exec 模式：不经过预处理直接执行，失败返回 null，DDL 返回 true，有行数返回 int。
 * MySQL 多语句需在配置 options 中设置 PDO::MYSQL_ATTR_MULTI_STATEMENTS => true。
 * 连接委托 resource\pdo() 管理，通过 container('#db.{configName}') 配置，默认配置名 default
 * @param object|string                       $sql        SQL 语句或 SQL helper 对象
 * @param array|string|int|callable|bool|null $params     参数数组；传入字符串/整数/可调用/bool/null 时作为 mode
 * @param string|int|callable|bool|null       $mode       操作模式；params 为数组时跳过此位的参数被视为 configName
 * @param string|null                         $configName 数据库配置名称，默认 'default'
 * @return mixed 查询结果，失败返回 null
 */
function db(object|string $sql, array|string|int|callable|bool|null $params = [], string|int|callable|bool|null $mode = null, ?string $configName = null): mixed{
	if(!is_array($params)) [$configName, $mode, $params] = [$mode, $params, []];
	if(is_object($sql) && (get_class($sql) === 'ff\helpers\sql' || is_a($sql, 'ff\helpers\sql', true))) [$sql, $params] = [(string)$sql, $sql->params];
	$configName = $configName ?? 'default';
	$pdo = pdo($configName);
	if(!$pdo) return null;
	$sqlUpper = trim(strtoupper($sql));
	if(in_array($sqlUpper, ['BEGIN', 'START TRANSACTION', 'BEGIN TRANSACTION'])) return $pdo->beginTransaction();
	if($sqlUpper === 'COMMIT') return $pdo->commit();
	if($sqlUpper === 'ROLLBACK') return $pdo->rollback();
	try{
		if($mode === 'exec') return match($r = $pdo->exec($sql)){ false => null, 0 => true, default => $r };
		$stmt = $pdo->prepare($sql);
		if(!$stmt->execute($params)) return null;
		return match (true) {
			is_string($mode) => match ($mode) {
				'row' => $stmt->fetch(\PDO::FETCH_ASSOC) ?: null,
				'list' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
				'value' => ($row = $stmt->fetch(\PDO::FETCH_NUM)) ? $row[0] : null,
				'column' => $stmt->fetchAll(\PDO::FETCH_COLUMN),
				'pairs' => $stmt->fetchAll(\PDO::FETCH_KEY_PAIR),
				'group' => $stmt->fetchAll(\PDO::FETCH_GROUP),
				'id' => $pdo->lastInsertId(),
				'count' => $stmt->rowCount(),
				'ok' => true,
				default => null
			},
			$mode === true => $stmt,
			is_int($mode) => $stmt->fetchAll($mode),
			is_callable($mode) => $mode($stmt, $pdo),
			default => true
		};
	}catch(\PDOException $e){
		return null;
	}
}