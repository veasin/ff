<?php
namespace nx;
/**
 * 数据库操作函数
 * @param object|string                       $sql        SQL语句
 * @param array|string|int|callable|bool|null $params     参数数组 或 mode（当省略params时）
 * @param string|int|callable|bool|null       $mode       操作模式 或 configName（当params为数组时）
 * @param string|null                         $configName 配置名称
 * @return mixed
 */
function db(object|string $sql, array|string|int|callable|bool|null $params = [], string|int|callable|bool|null $mode = null, ?string $configName = null): mixed{
	static $connections = [];
	if(!is_array($params)) [$configName, $mode, $params] = [$mode, $params, []];
	if(is_object($sql) && (get_class($sql) === 'nx\helpers\sql' || is_a($sql, 'nx\helpers\sql', true))) [$sql, $params] = [(string)$sql, $sql->params];
	$configName = $configName ?? 'default';
	if(!isset($connections[$configName])){
		$config = container("db.{$configName}") ?? null;
		if(!is_array($config) || !isset($config['dsn'])) return null;
		try{
			$connections[$configName] = new \PDO($config['dsn'], $config['username'] ?? null, $config['password'] ?? null, ($config['options'] ?? []) + [
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
					\PDO::ATTR_EMULATE_PREPARES => false,
				]);
		}catch(\PDOException $e){
			return null;
		}
	}
	$pdo = $connections[$configName];
	$sqlUpper = trim(strtoupper($sql));
	if(in_array($sqlUpper, ['BEGIN', 'START TRANSACTION', 'BEGIN TRANSACTION'])) return $pdo->beginTransaction();
	if($sqlUpper === 'COMMIT') return $pdo->commit();
	if($sqlUpper === 'ROLLBACK') return $pdo->rollback();
	if(str_starts_with($sqlUpper, 'SAVEPOINT ') || str_starts_with($sqlUpper, 'ROLLBACK TO SAVEPOINT ')) return $pdo->exec($sql);
	try{
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