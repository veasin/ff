<?php
declare(strict_types=1);
namespace ff;
/**
 * 数组编译为 SQL 字符串，支持 MySQL/SQLite 方言。
 * ```
 * sql(['SELECT', 'fields' => ['id', 'name'], 'from' => 'user']);//SELECT `id`, `name` FROM `user`
 * sql(['SELECT', 'fields' => [['COUNT', '*'], 'total' => ['COUNT', '*']], 'from' => 'user', 'where' => ['id' => 1]]);//SELECT COUNT(*), COUNT(*) `total` FROM `user` WHERE `id` = 1
 * sql(['INSERT', 'table' => 'user', 'value' => ['name' => 'vea', 'age' => 30]]);//INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30)
 * sql(['UPDATE', 'from' => 'user', 'set' => ['name' => 'newname'], 'where' => ['id' => 1]]);//UPDATE `user` SET `name` = 'newname' WHERE `id` = 1
 * sql(['DELETE', 'from' => 'user', 'where' => ['status' => 0]]);//DELETE FROM `user` WHERE `status` = 0
 * sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1]], ['param' => true]);//['SELECT * FROM `user` WHERE `id` = ?', [1]]
 * ```
 * @param array $query   数组描述的 SQL 语句
 * @param string|array $options 方言名或选项数组，选项支持 'dialect'、'param'
 * @return string|array|null 编译后的 SQL 字符串，param 模式返回 [sql, params]
 */
function sql(array $query, string|array $options = []): string|array|null{
	if(is_string($options)){
		$dialect = $options;
		$options = [];
	}else{
		$dialect = $options['dialect'] ?? 'mysql';
	}
	static $defaultCfg = [
		'limitArray' => 'comma',
		'replace' => 'REPLACE',
		'onConflict' => 'ON DUPLICATE KEY UPDATE',
		'lock' => true,
		'straightJoin' => true,
		'cube' => true,
	];
	static $cfgCache = [];
	if(!isset($cfgCache[$dialect])){
		$userCfg = container("#sql.{$dialect}") ?? [];
		$cfgCache[$dialect] = array_merge($defaultCfg, $userCfg);
	}
	static $_cfg = null, $params = [], $useParam = false;
	$_cfg = $cfgCache[$dialect];
	$params = [];
	$useParam = !empty($options['param']);
	static $isId = static fn($s) => preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*(?:\.[a-zA-Z_$][a-zA-Z0-9_$]*){0,3}$/D', $s) === 1;
	static $id = null, $str = null, $val = null, $expr = null, $compileCase = null, $cond = null, $compileFields = null, $parseTable = null, $compileOn = null, $compileJoin = null, $compileOrder = null, $compileLimit = null, $compileSelect = null, $compileInsert = null, $compileUpdate = null, $compileDelete = null, $compileSetOp = null, $compile = null, $compileWith = null;
	if($id === null){
		$id = static function($s) use ($isId): string{
			if(!is_string($s)) return (string)$s;
			if($s === '*') return '*';
			if(str_ends_with($s, '.*') && preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', substr($s, 0, -2))) return '`' . substr($s, 0, -2) . '`.*';
			if($isId($s)) return implode('.', array_map(fn($p) => "`{$p}`", explode('.', $s)));
			return "'" . str_replace("'", "\\'", $s) . "'";
		};
		$str = static function($s): string{
			return "'" . str_replace("'", "\\'", $s) . "'";
		};
		$push = static function($v) use (&$params): string{ $params[] = $v; return '?'; };
		$val = static function($v, bool $asLit = false) use (&$val, &$expr, &$compile, $id, $str, &$params, &$useParam, $push): string{
			if($useParam && $asLit){
				return match(true){
					$v === null => 'NULL',
					is_bool($v) => $push($v ? 1 : 0),
					is_int($v), is_float($v) => $push($v),
					is_string($v) => $push($v),
					is_array($v) => match(true){
						$v === [] => "''",
						$v[0] === 's' => $push($v[1]),
						$v[0] === 'RAW' => (string)($v[1] ?? ''),
						is_string($v[0]) && strtoupper($v[0]) === 'SELECT' => '(' . ($compile($v) ?? '') . ')',
						default => $expr($v),
					},
					default => 'NULL',
				};
			}
			return match(true){
				$v === null => 'NULL',
				is_bool($v) => $v ? 'TRUE' : 'FALSE',
				is_int($v), is_float($v) => (string)$v,
				is_string($v) => $asLit ? $str($v) : $id($v),
				is_array($v) => match(true){
					$v === [] => "''",
				$v[0] === 's' => $useParam ? $push($v[1]) : $str($v[1]),
				$v[0] === 'RAW' => (string)($v[1] ?? ''),
					is_string($v[0]) && strtoupper($v[0]) === 'SELECT' => '(' . ($compile($v) ?? '') . ')',
					default => $expr($v),
				},
				default => 'NULL',
			};
		};
		$expr = static function(array $e) use (&$expr, &$val, $id, &$compile, &$compileCase, $isId, $str, &$params, &$useParam): string{
			$op = $e[0] ?? '';
			$hasOver = false;
			foreach($e as $k => $v){
				if(is_string($k) && strtoupper($k) === 'OVER'){
					$hasOver = true;
					break;
				}
			}
			if($hasOver){
				$fnArgs = [];
				$overSpec = null;
				foreach($e as $k => $v){
					if($k === 0) continue;
					if(is_string($k) && strtoupper($k) === 'OVER'){
						$overSpec = $v;
						continue;
					}
					$fnArgs[] = $val($v);
				}
				$sql = $op . '(' . implode(', ', $fnArgs) . ') OVER (';
				$overParts = [];
				if(isset($overSpec['PARTITION'])){
					$parts = is_array($overSpec['PARTITION']) ? $overSpec['PARTITION'] : [$overSpec['PARTITION']];
					$overParts[] = 'PARTITION BY ' . implode(', ', array_map($id, $parts));
				}
				if(isset($overSpec['ORDER'])){
					$orderParts = [];
					foreach($overSpec['ORDER'] as $fk => $fv){
						if(is_string($fk)) $orderParts[] = $id($fk) . ' ' . $fv;
						else $orderParts[] = $id($fv);
					}
					$overParts[] = 'ORDER BY ' . implode(', ', $orderParts);
				}
				if(isset($overSpec['frame'])){
					$fr = $overSpec['frame'];
					$overParts[] = count($fr) >= 2 ? $fr[0] . ' BETWEEN ' . implode(' ', array_slice($fr, 1)) : implode(' ', $fr);
				}
				return $sql . implode(' ', $overParts) . ')';
			}
			$args = array_slice($e, 1);
			static $ops = ['gt' => '>', 'eq' => '=', 'ne' => '!=', 'lt' => '<', 'le' => '<=', 'ge' => '>=', 'like' => 'LIKE', 'add' => '+', 'sub' => '-', 'mul' => '*', 'div' => '/', 'mod' => '%'];
			$opLower = strtolower($op);
			if(isset($ops[$opLower])) return $val($args[0]) . ' ' . $ops[$opLower] . ' ' . $val($args[1]);
			return match(true){
				$op === 'RAW' => (string)($e[1] ?? ''),
				$op === 's' => $val($e[1], true),
				$op === 'CASE' || $op === 'case' => $compileCase($e),
				in_array($op, ['NOT', 'not'], true) => 'NOT ' . $val($args[0]),
				in_array($op, ['isnull', 'IS NULL'], true) => $val($args[0]) . ' IS NULL',
				in_array($op, ['is not null', 'IS NOT NULL'], true) => $val($args[0]) . ' IS NOT NULL',
				in_array($op, ['EXISTS'], true) => 'EXISTS ' . $val($args[0]),
				in_array($op, ['NOT EXISTS'], true) => 'NOT EXISTS ' . $val($args[0]),
				in_array($op, ['and', 'AND'], true) => '(' . implode(' AND ', array_map($val, $args)) . ')',
				in_array($op, ['or', 'OR'], true) => '(' . implode(' OR ', array_map($val, $args)) . ')',
				in_array($op, ['in', 'IN'], true) => $val($args[0]) . ' IN (' . implode(', ', array_map(fn($a) => is_array($a) && is_string($a[0] ?? null) && strtoupper($a[0]) === 'SELECT' ? ($compile($a) ?? '')
						: $val($a), array_slice($args, 1))) . ')',
				in_array($op, ['not in', 'NOT IN'], true) => $val($args[0]) . ' NOT IN (' . implode(', ', array_map(fn($a) => is_array($a) && is_string($a[0] ?? null) && strtoupper($a[0]) === 'SELECT' ? ($compile($a) ?? '')
						: $val($a), array_slice($args, 1))) . ')',
				in_array($op, ['between', 'BETWEEN'], true) => $val($args[0]) . ' BETWEEN ' . $val($args[1]) . ' AND ' . $val($args[2]),
				default => $op . '(' . implode(', ', array_map($val, $args)) . ')',
			};
		};
		$compileCase = static function(array $e) use (&$val): string{
			$parts = ['CASE'];
			$args = array_slice($e, 1);
			$i = 0;
			if(isset($args[0]) && is_string($args[0])){
				$parts[] = ' ' . $val($args[0]);
				$i = 1;
			}
			for(; $i < count($args); $i++){
				$cl = $args[$i];
				if(!is_array($cl) || !isset($cl[0])) continue;
				if($cl[0] === 'WHEN') $parts[] = ' WHEN ' . $val($cl[1]) . ' THEN ' . $val($cl[2]);
				elseif($cl[0] === 'ELSE') $parts[] = ' ELSE ' . $val($cl[1]);
			}
			$parts[] = ' END';
			return implode('', $parts);
		};
		$cond = static function(array $c) use (&$cond, &$expr, &$val, $id, &$compile): string{
			if(array_is_list($c) && count($c) >= 2 && is_string($c[0]) && !in_array(strtoupper($c[0]), ['AND', 'OR'], true)) return $expr($c);
			$connector = 'AND';
			$hasExplicitConnector = false;
			$hasKv = false;
			$parts = $c;
			if(isset($c[0]) && is_string($c[0]) && in_array(strtoupper($c[0]), ['AND', 'OR'], true)){
				$connector = strtoupper($c[0]);
				$parts = array_slice($c, 1);
				$hasExplicitConnector = true;
			}
			$clauses = [];
			foreach($parts as $k => $v){
				if(is_string($k)){
					$hasKv = true;
					$f = $id($k);
					if($v === null) $clauses[] = "{$f} IS NULL";
					elseif(!is_array($v)) $clauses[] = "{$f} = " . $val($v, true);
					else{
						$first = $v[0] ?? null;
						if(!is_string($first)) $clauses[] = "{$f} IN (" . implode(', ', array_map(fn($x) => $val($x, true), $v)) . ')';
						else{
							$knownOps = ['gt', 'eq', 'ne', 'lt', 'le', 'ge', 'like', 'in', 'not in', 'between', 'isnull', 'is not null', 'add', 'sub', 'mul', 'div', 'mod', 'and', 'or', 'not'];
							$opName = in_array(strtolower($first), $knownOps, true) ? strtolower($first) : $first;
							$clauses[] = $expr(array_merge([$opName, $k], array_slice($v, 1)));
						}
					}
				}
				elseif(is_array($v)){
					if(is_string($v[0] ?? null) && in_array(strtoupper($v[0]), ['AND', 'OR'], true)) $clauses[] = $cond($v);
					else $clauses[] = $expr($v);
				}
			}
			$sql = implode(" {$connector} ", $clauses);
			$needsWrap = $connector === 'OR' || ($hasExplicitConnector && count($parts) > 1 && !$hasKv);
			return $needsWrap ? '(' . $sql . ')' : $sql;
		};
		$compileFields = static function($fields) use ($id, &$val): string{
			if(empty($fields)) return '*';
			$parts = [];
			foreach($fields as $alias => $field){
				if(is_string($alias)) $parts[] = (is_array($field) ? $val($field) : $id($field)) . ' ' . $id($alias);
				else $parts[] = is_array($field) ? $val($field) : (is_string($field) ? $id($field) : $val($field, true));
			}
			return implode(', ', $parts);
		};
		$parseTable = static function($from, $prependFrom = false) use ($id, &$compile): string{
			if($from === null || $from === '') return '';
			$result = match(true){
				is_string($from) => preg_match('/^([a-zA-Z_$][a-zA-Z0-9_$]*(?:\.[a-zA-Z_$][a-zA-Z0-9_$]*){0,3}) ([a-zA-Z_$][a-zA-Z0-9_$]*)$/', $from, $m) ? $id($m[1]) . ' ' . $id($m[2]) : $id($from),
				array_is_list($from) => implode(', ', array_map($id, $from)),
				default => implode(', ', array_map(fn($alias, $table) => is_array($table) ? '(' . ($compile($table) ?? '') . ') ' . $id((string)$alias) : $id((string)$table) . ' ' . $id((string)$alias), array_keys($from), $from)),
			};
			return $prependFrom ? 'FROM ' . $result : $result;
		};
		$compileOn = static function($on) use ($id, &$cond): string{
			if(array_is_list($on)) return '(' . $cond($on) . ')';
			$parts = [];
			foreach($on as $k => $v) $parts[] = $id((string)$k) . ' = ' . $id((string)$v);
			return '(' . implode(' AND ', $parts) . ')';
		};
		$compileJoin = static function($joins) use ($id, $parseTable, &$compileOn, &$_cfg): string{
			$sql = '';
			foreach($joins as $j){
				if(!isset($j['table'])) continue;
				$type = isset($j['type']) ? strtoupper($j['type']) : 'LEFT';
				$typeMap = ['LEFT' => 'LEFT JOIN', 'INNER' => 'INNER JOIN', 'RIGHT' => 'RIGHT JOIN', 'CROSS' => 'CROSS JOIN', 'NATURAL' => 'NATURAL JOIN'];
				if($_cfg['straightJoin']) $typeMap['STRAIGHT'] = 'STRAIGHT_JOIN';
				$kw = $typeMap[$type] ?? 'LEFT JOIN';
				$tableSql = $parseTable($j['table']);
				$sql .= ' ' . $kw . ' ' . $tableSql;
				if(isset($j['on'])) $sql .= ' ON ' . $compileOn($j['on']);
				if(isset($j['using'])){
					$using = is_array($j['using']) ? $j['using'] : [$j['using']];
					$sql .= ' USING (' . implode(', ', array_map($id, $using)) . ')';
				}
			}
			return $sql;
		};
		$compileOrder = static function($order) use ($id, &$val): string{
			$parts = [];
			foreach($order as $k => $v){
				if(is_string($k)) $parts[] = $id($k) . ' ' . (is_array($v) ? implode(' ', $v) : $v);
				elseif(is_array($v)) $parts[] = $val($v);
				else $parts[] = $id($v);
			}
			return 'ORDER BY ' . implode(', ', $parts);
		};
		$compileLimit = static function($limit, $offset = null) use (&$_cfg): string{
			if(is_array($limit)){
				if($_cfg['limitArray'] === 'standard') return 'LIMIT ' . (int)$limit[1] . ' OFFSET ' . (int)$limit[0];
				return 'LIMIT ' . (int)$limit[0] . ', ' . (int)$limit[1];
			}
			$sql = 'LIMIT ' . (int)$limit;
			if($offset !== null) $sql .= ' OFFSET ' . (int)$offset;
			return $sql;
		};
		$compileSelect = static function(array $qry) use (&$compile, &$compileFields, $parseTable, &$compileJoin, &$compileOrder, &$compileLimit, &$cond, $id, &$_cfg): string{
			$sql = 'SELECT';
			if(!empty($qry['distinct'])) $sql .= ' DISTINCT';
			$sql .= ' ' . (isset($qry['fields']) ? $compileFields($qry['fields']) : '*');
			if(isset($qry['from'])) $sql .= ' ' . $parseTable($qry['from'], true);
			if(!empty($qry['join'])) $sql .= $compileJoin($qry['join']);
			if(isset($qry['where']) && !empty($qry['where'])) $sql .= ' WHERE ' . $cond($qry['where']);
			if(!empty($qry['group'])) $sql .= ' GROUP BY ' . implode(', ', array_map($id, $qry['group']));
			if(isset($qry['rollup']) && $qry['rollup']) $sql .= ' WITH ROLLUP';
			if(isset($qry['cube']) && $_cfg['cube'] && $qry['cube']) $sql .= ' WITH CUBE';
			if(isset($qry['having']) && !empty($qry['having'])) $sql .= ' HAVING ' . $cond($qry['having']);
			if(!empty($qry['order'])) $sql .= ' ' . $compileOrder($qry['order']);
			if(isset($qry['limit'])) $sql .= ' ' . $compileLimit($qry['limit'], $qry['offset'] ?? null);
			if(isset($qry['lock']) && $_cfg['lock']){
				$sql .= ' FOR';
				if(is_array($qry['lock'])) $sql .= ' ' . $qry['lock'][0] . ' ' . $qry['lock'][1];
				else $sql .= ' ' . $qry['lock'];
			}
			return $sql;
		};
		$compileInsert = static function(array $qry) use ($id, &$compile, &$val, &$_cfg): ?string{
			if(!isset($qry['table']) || (!isset($qry['value']) && empty($qry['values']) && !isset($qry['select']))) return null;
			$cmd = !empty($qry['replace']) ? $_cfg['replace'] : 'INSERT';
			$sql = $cmd . ' INTO ' . $id($qry['table']);
			if(isset($qry['select'])) return $sql . ' ' . ($compile($qry['select']) ?? '');
			if(isset($qry['value'])){
				$row = $qry['value'];
				$cols = array_keys($row);
				$vals = array_map(fn($v) => $val($v, true), array_values($row));
				$sql .= ' (' . implode(', ', array_map($id, $cols)) . ') VALUES (' . implode(', ', $vals) . ')';
			}
			elseif(isset($qry['values'])){
				$cols = array_keys($qry['values'][0]);
				$sql .= ' (' . implode(', ', array_map($id, $cols)) . ') VALUES ';
				$rows = [];
				foreach($qry['values'] as $row) $rows[] = '(' . implode(', ', array_map(fn($v) => $val($v, true), array_values($row))) . ')';
				$sql .= implode(', ', $rows);
			}
			if(isset($qry['update'])){
				$parts = [];
				foreach($qry['update'] as $k => $v) $parts[] = $id($k) . ' = ' . $val($v, true);
				$sql .= ' ' . $_cfg['onConflict'] . ' ' . implode(', ', $parts);
			}
			return $sql;
		};
		$compileUpdate = static function(array $qry) use ($id, $parseTable, &$compileJoin, &$compileOrder, &$compileLimit, &$cond, &$val): ?string{
			if(!isset($qry['from']) || !isset($qry['set'])) return null;
			$sql = 'UPDATE ' . $parseTable($qry['from']);
			if(!empty($qry['join'])) $sql .= $compileJoin($qry['join']);
			$setParts = [];
			foreach($qry['set'] as $k => $v){
				if(is_array($v) && $v[0] === 'RAW') $setParts[] = $id($k) . ' = ' . $v[1];
				else $setParts[] = $id($k) . ' = ' . $val($v, true);
			}
			$sql .= ' SET ' . implode(', ', $setParts);
			if(isset($qry['where']) && !empty($qry['where'])) $sql .= ' WHERE ' . $cond($qry['where']);
			if(!empty($qry['order'])) $sql .= ' ' . $compileOrder($qry['order']);
			if(isset($qry['limit'])) $sql .= ' ' . $compileLimit($qry['limit']);
			return $sql;
		};
		$compileDelete = static function(array $qry) use ($id, $parseTable, &$compileJoin, &$compileOrder, &$compileLimit, &$cond): ?string{
			if(!isset($qry['from'])) return null;
			$sql = 'DELETE';
			if(!empty($qry['delete'])){
				$targets = is_array($qry['delete']) ? $qry['delete'] : [$qry['delete']];
				$sql .= ' ' . implode(', ', array_map($id, $targets));
			}
			$sql .= ' ' . $parseTable($qry['from'], true);
			if(!empty($qry['join'])) $sql .= $compileJoin($qry['join']);
			if(isset($qry['where']) && !empty($qry['where'])) $sql .= ' WHERE ' . $cond($qry['where']);
			if(!empty($qry['order'])) $sql .= ' ' . $compileOrder($qry['order']);
			if(isset($qry['limit'])) $sql .= ' ' . $compileLimit($qry['limit']);
			return $sql;
		};
		$compileSetOp = static function(array $qry) use (&$compile, &$compileOrder, &$compileLimit): ?string{
			$op = strtoupper($qry[0]);
			$type = isset($qry['type']) ? ' ' . $qry['type'] : '';
			$queries = [];
			$order = $qry['order'] ?? null;
			$limit = $qry['limit'] ?? null;
			$offset = $qry['offset'] ?? null;
			foreach($qry as $k => $v){
				if(is_string($k)) continue;
				if(is_array($v)) $queries[] = ($compile($v) ?? '');
			}
			if(empty($queries)) return null;
			$sql = implode("\n{$op}{$type}\n", $queries);
			if($order || $limit !== null){
				$sql = "(\n" . preg_replace('/^/m', '  ', $sql) . "\n)";
				if($order) $sql .= "\n" . $compileOrder($order);
				if($limit !== null) $sql .= "\n" . $compileLimit($limit, $offset);
			}
			return $sql;
		};
		$compile = static function(array $qry) use (&$compile, &$compileSelect, &$compileInsert, &$compileUpdate, &$compileDelete, &$compileSetOp, &$compileWith): ?string{
			$upper = is_string($qry[0] ?? null) ? strtoupper($qry[0]) : '';
			return match($upper){
				'SELECT' => $compileSelect($qry),
				'INSERT' => $compileInsert($qry),
				'UPDATE' => $compileUpdate($qry),
				'DELETE' => $compileDelete($qry),
				'UNION', 'EXCEPT', 'INTERSECT' => $compileSetOp($qry),
				'WITH' => $compileWith($qry),
				default => null,
			};
		};
		$compileWith = static function(array $qry) use (&$compile, $id): ?string{
			$recursive = !empty($qry['recursive']);
			$ctes = [];
			$main = null;
			foreach($qry as $k => $v){
				if($k === 'recursive' || $k === 0) continue;
				if(is_string($k) && is_array($v)) $ctes[] = $id($k) . ' AS (' . "\n" . ($compile($v) ?? '') . "\n" . ')';
				elseif(is_array($v)) $main = $v;
			}
			if(empty($ctes) && !$main) return null;
			$sql = 'WITH';
			if($recursive) $sql .= ' RECURSIVE';
			$sql .= "\n" . implode(",\n", $ctes) . "\n";
			if($main) $sql .= ($compile($main) ?? '');
			return $sql;
		};
	}
	$result = $compile($query);
	return $useParam ? [$result, $params] : $result;
}
