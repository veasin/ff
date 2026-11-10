<?php
declare(strict_types=1);
namespace ff;
/**
 * 数组编译为 SQL 字符串，支持 MySQL/SQLite 方言。
 * 编译器只做"数组结构 → SQL 文本"的翻译：识别字面量/RAW/标识符/通配符，
 * 按当前表上下文补限定名或退化，不校验名字合法性、不静默纠正用户明确传入的值。
 * 不合规的输入原样进入 SQL，由数据库报错。
 * ```
 * sql(['SELECT', 'fields' => ['id', 'name'], 'from' => 'user']);//SELECT `id`, `name` FROM `user`
 * sql(['SELECT', 'fields' => ['id'], 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'id'], 'fields' => ['nickname']]]]);//SELECT `u`.`id`, `i`.`nickname` FROM `user` `u` LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
 * sql(['INSERT', 'table' => 'user', 'value' => ['name' => "'vea'", 'age' => 30]]);//INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30)
 * sql(['UPDATE', 'from' => 'user', 'set' => ['name' => "'newname'"], 'where' => ['id' => 1]]);//UPDATE `user` SET `name` = 'newname' WHERE `id` = 1
 * sql(['DELETE', 'from' => 'user', 'where' => ['status' => 0]]);//DELETE FROM `user` WHERE `status` = 0
 * sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1]], ['param' => true]);//['SELECT * FROM `user` WHERE `id` = ?', [1]]
 * ```
 * @param array        $query   数组描述的 SQL 语句
 * @param string|array $options 方言名或选项数组，选项支持 'dialect'、'param'
 * @return string|array|null 编译后的 SQL 字符串，param 模式返回 [sql, params]
 */
function sql(array $query, string|array $options = []): string|array|null{
	if(is_string($options)) [$dialect, $options] = [$options, []];
	else $dialect = $options['dialect'] ?? 'mysql';
	static $defaultCfg = [
		'limitArray' => 'comma',
		'replace' => 'REPLACE',
		'onConflict' => 'ON DUPLICATE KEY UPDATE',
		'boolLiteral' => true,
	];
	static $cfgCache = [];
	if(!isset($cfgCache[$dialect])){
		$userCfg = container("#sql.$dialect") ?? [];
		$cfgCache[$dialect] = array_merge($defaultCfg, $userCfg);
	}
	static $_cfg = null, $params = [], $useParam = false;
	$_cfg = $cfgCache[$dialect];
	$params = [];
	$useParam = !empty($options['param']);
	static $compile = null;
	if($compile === null){
		$seg = static fn(string $p): string => '`' . str_replace('`', '``', $p) . '`';
		$push = static function($v) use (&$params): string{
			$params[] = $v;
			return '?';
		};
		$lit = static function(string $s) use (&$params, &$useParam, $push): ?string{
			$n = strlen($s);
			if($n < 2 || $s[0] !== $s[$n - 1] || ($s[0] !== "'" && $s[0] !== '"')) return null;
			$inner = substr($s, 1, -1);
			return $useParam ? $push($inner) : "'" . str_replace("'", "''", $inner) . "'";
		};
		$id = static function($s, ?array $cctx = null) use (&$id, $seg): string{
			if(!is_string($s)) return (string)$s;
			$n = strlen($s);
			if($s === '*') return '*';
			if($n >= 2 && $s[0] === $s[$n - 1] && ($s[0] === "'" || $s[0] === '"')) return "'" . str_replace("'", "''", substr($s, 1, -1)) . "'";
			if($n >= 2 && $s[0] === '`' && $s[$n - 1] === '`') return substr($s, 1, -1);
			if($n > 2 && str_ends_with($s, '.*')){
				$pfx = substr($s, 0, -2);
				return $cctx !== null && !$cctx[1] && $pfx === $cctx[0] ? '*' : $seg($pfx) . '.*';
			}
			if($cctx !== null){
				[$lim, $multi] = $cctx;
				if(!$multi && str_starts_with($s, "{$lim}.")) return $id(substr($s, strlen($lim) + 1));
				if($multi && !str_contains($s, '.')) return $seg($lim) . '.' . $seg($s);
			}
			return str_contains($s, '.') ? implode('.', array_map($seg, explode('.', $s))) : $seg($s);
		};
		$val = static function($v, bool $bind = false, ?array $cctx = null, ?array $rctx = null) use (&$expr, &$compile, $id, $lit, &$useParam, &$_cfg, $push): string{
			return match (true) {
				$v === null => 'NULL',
				is_bool($v) => $useParam && $bind ? $push($v ? 1 : 0) : ($_cfg['boolLiteral'] ? ($v ? 'TRUE' : 'FALSE') : ($v ? '1' : '0')),
				is_int($v), is_float($v) => $useParam && $bind ? $push($v) : (string)$v,
				is_string($v) => $lit($v) ?? $id($v, $cctx),
				is_array($v) => match (true) {
					$v === [] => "''",
					is_string($v[0] ?? null) && strtoupper($v[0]) === 'SELECT' => '(' . ($compile($v) ?? '') . ')',
					default => $expr($v, $cctx, $rctx),
				},
				default => 'NULL',
			};
		};
		$expr = static function(array $e, ?array $cctx = null, ?array $rctx = null) use (&$expr, &$val, &$compile, &$compileCase, $id): string{
			$op = $e[0] ?? '';
			$fnArgs = [];
			$overSpec = null;
			$hasOver = false;
			foreach($e as $k => $v){
				if($k === 0) continue;
				if(is_string($k) && strtoupper($k) === 'OVER'){
					$hasOver = true;
					$overSpec = $v;
				}
				else $fnArgs[] = $v;
			}
			if($hasOver){
				$fnSql = array_map(fn($v) => $val($v, false, $cctx), $fnArgs);
				$overParts = [];
				if(isset($overSpec['PARTITION'])){
					$parts = is_array($overSpec['PARTITION']) ? $overSpec['PARTITION'] : [$overSpec['PARTITION']];
					$overParts[] = 'PARTITION BY ' . implode(', ', array_map(fn($p) => $id($p, $cctx), $parts));
				}
				if(isset($overSpec['ORDER'])){
					$orderParts = [];
					foreach($overSpec['ORDER'] as $fk => $fv){
						if(is_string($fk)) $orderParts[] = $id($fk, $cctx) . ' ' . $fv;
						else $orderParts[] = $id($fv, $cctx);
					}
					$overParts[] = 'ORDER BY ' . implode(', ', $orderParts);
				}
				if(isset($overSpec['frame'])){
					$fr = $overSpec['frame'];
					$overParts[] = count($fr) >= 2 ? $fr[0] . ' BETWEEN ' . implode(' ', array_slice($fr, 1)) : implode(' ', $fr);
				}
				return $op . '(' . implode(', ', $fnSql) . ') OVER (' . implode(' ', $overParts) . ')';
			}
			$args = $fnArgs;
			static $ops = ['=' => 1, '!=' => 1, '<>' => 1, '<' => 1, '<=' => 1, '>' => 1, '>=' => 1, '+' => 1, '-' => 1, '*' => 1, '/' => 1, '%' => 1];
			if(isset($ops[$op])) return $val($args[0], false, $cctx) . ' ' . $op . ' ' . $val($args[1], false, $rctx ?? $cctx);
			return match (strtoupper($op)) {
				'CASE' => $compileCase($e, $cctx),
				'NOT' => 'NOT ' . $val($args[0], false, $cctx, $rctx),
				'IS NULL' => $val($args[0], false, $cctx) . ' IS NULL',
				'IS NOT NULL' => $val($args[0], false, $cctx) . ' IS NOT NULL',
				'EXISTS' => 'EXISTS ' . $val($args[0], false, $cctx),
				'NOT EXISTS' => 'NOT EXISTS ' . $val($args[0], false, $cctx),
				'AND' => '(' . implode(' AND ', array_map(fn($a) => $val($a, false, $cctx, $rctx), $args)) . ')',
				'OR' => '(' . implode(' OR ', array_map(fn($a) => $val($a, false, $cctx, $rctx), $args)) . ')',
				'IN' => $val($args[0], false, $cctx) . ' IN (' . implode(', ', array_map(fn($a) => is_array($a) && is_string($a[0] ?? null) && strtoupper($a[0]) === 'SELECT'
						? ($compile($a) ?? '')
						: $val($a, true, $rctx ?? $cctx), array_slice($args, 1))) . ')',
				'NOT IN' => $val($args[0], false, $cctx) . ' NOT IN (' . implode(', ', array_map(fn($a) => is_array($a) && is_string($a[0] ?? null) && strtoupper($a[0]) === 'SELECT' ? ($compile($a) ?? '')
						: $val($a, true, $rctx ?? $cctx), array_slice($args, 1))) . ')',
				'BETWEEN' => $val($args[0], false, $cctx) . ' BETWEEN ' . $val($args[1], true, $cctx) . ' AND ' . $val($args[2], true, $cctx),
				'LIKE' => $val($args[0], false, $cctx) . ' LIKE ' . $val($args[1], false, $rctx ?? $cctx),
				default => $op . '(' . implode(', ', array_map(fn($a) => $val($a, false, $cctx), $args)) . ')',
			};
		};
		$compileCase = static function(array $e, ?array $cctx = null) use (&$val): string{
			$parts = ['CASE'];
			$args = array_slice($e, 1);
			$i = 0;
			if(isset($args[0]) && is_string($args[0])){
				$parts[] = ' ' . $val($args[0], false, $cctx);
				$i = 1;
			}
			for(; $i < count($args); $i++){
				$cl = $args[$i];
				if(!is_array($cl) || !isset($cl[0])) continue;
				if($cl[0] === 'WHEN') $parts[] = ' WHEN ' . $val($cl[1], false, $cctx) . ' THEN ' . $val($cl[2], false, $cctx);
				elseif($cl[0] === 'ELSE') $parts[] = ' ELSE ' . $val($cl[1], false, $cctx);
			}
			$parts[] = ' END';
			return implode('', $parts);
		};
		$cond = static function(array $c, ?array $cctx = null, ?array $rctx = null) use (&$cond, &$expr, &$val, $id): string{
			$head = isset($c[0]) && is_string($c[0]) ? strtoupper($c[0]) : null;
			$isLogic = $head === 'AND' || $head === 'OR';
			if(array_is_list($c) && $head !== null && !$isLogic) return $expr($c, $cctx, $rctx);
			$connector = $isLogic ? $head : 'AND';
			$parts = $isLogic ? array_slice($c, 1) : $c;
			$hasKv = false;
			$clauses = [];
			foreach($parts as $k => $v){
				if(is_string($k)){
					$hasKv = true;
					$f = $id($k, $cctx);
					if($v === null) $clauses[] = "{$f} IS NULL";
					elseif(is_array($v)){
						$first = $v[0] ?? null;
						if(!is_string($first)) $clauses[] = "{$f} IN (" . implode(', ', array_map(fn($x) => $val($x, true, $rctx ?? $cctx), $v)) . ')';
						else $clauses[] = $expr(array_merge([$first, $k], array_slice($v, 1)), $cctx, $rctx);
					}
					else $clauses[] = "{$f} = " . $val($v, true, $rctx ?? $cctx);
				}
				elseif(is_array($v)){
					if(is_string($v[0] ?? null) && in_array(strtoupper($v[0]), ['AND', 'OR'], true)) $clauses[] = $cond($v, $cctx, $rctx);
					else $clauses[] = $expr($v, $cctx, $rctx);
				}
			}
			$sql = implode(" {$connector} ", $clauses);
			$needsWrap = $connector === 'OR' || ($isLogic && count($parts) > 1 && !$hasKv);
			return $needsWrap ? '(' . $sql . ')' : $sql;
		};
		$compileFields = static function($fields, ?array $cctx) use ($id, &$val): string{
			if($fields === []) return $cctx === null || !$cctx[1] ? '*' : $id($cctx[0]) . '.*';
			$parts = [];
			foreach($fields as $alias => $field){
				$sql = is_array($field) ? $val($field, false, $cctx) : (is_string($field) ? $id($field, $cctx) : $val($field));
				$parts[] = is_string($alias) ? $sql . ' ' . $id($alias) : $sql;
			}
			return implode(', ', $parts);
		};
		$srcOf = static function($src): array{
			if(is_string($src)){
				$n = strlen($src);
				if($n >= 2 && $src[0] === '`' && $src[$n - 1] === '`') return [$src, $src];
				$pos = strrpos($src, ' ');
				return $pos === false ? [$src, $src] : [substr($src, 0, $pos), substr($src, $pos + 1)];
			}
			return match (true) {
				is_array($src) && !array_is_list($src) => [$src[array_key_first($src)], (string)array_key_first($src)],
				is_array($src) => [$src, null],
				default => [(string)$src, (string)$src],
			};
		};
		$sources = static function($from) use ($srcOf): array{
			if($from === null || $from === '') return [];
			if(is_string($from)) return [$srcOf($from)];
			$out = [];
			if(array_is_list($from)){
				foreach($from as $item) if(is_string($item)) $out[] = $srcOf($item);
			}
			else foreach($from as $alias => $entity) $out[] = [$entity, (string)$alias];
			return $out;
		};
		$resolveCtx = static function(array $qry) use ($sources): array{
			$srcs = $sources($qry['from'] ?? null);
			$joinCnt = 0;
			foreach($qry['join'] ?? [] as $j) if(isset($j['table'])) $joinCnt++;
			$multi = count($srcs) + $joinCnt > 1;
			return [$srcs, $multi, $srcs === [] ? null : [$srcs[0][1], $multi]];
		};
		$parseTable = static function($from, bool $prependFrom = false, bool $multi = false) use ($id, &$compile, $sources): string{
			$parts = [];
			foreach($sources($from) as [$entity, $lim]){
				$body = is_array($entity) ? '(' . ($compile($entity) ?? '') . ')' : $id($entity);
				$alias = (!$multi || $lim === null || (!is_array($entity) && $lim === $entity)) ? '' : ' ' . $id($lim);
				$parts[] = $body . $alias;
			}
			$result = implode(', ', $parts);
			return $prependFrom ? 'FROM ' . $result : $result;
		};
		$compileOn = static function($on, ?array $jctx, ?array $dctx) use ($id, &$val, &$cond): string{
			if(array_is_list($on)) return '(' . $cond($on, $jctx, $dctx) . ')';
			$parts = [];
			foreach($on as $k => $v) $parts[] = $id((string)$k, $jctx) . ' = ' . $val($v, false, $dctx);
			return '(' . implode(' AND ', $parts) . ')';
		};
		$compileJoin = static function(array $joins, ?array $dctx) use ($id, $parseTable, &$compileOn, $srcOf): string{
			static $typeMap = ['LEFT' => 'LEFT JOIN', 'INNER' => 'INNER JOIN', 'RIGHT' => 'RIGHT JOIN', 'CROSS' => 'CROSS JOIN', 'NATURAL' => 'NATURAL JOIN', 'STRAIGHT' => 'STRAIGHT_JOIN'];
			$sql = '';
			foreach($joins as $j){
				$type = strtoupper($j['type'] ?? '');
				if($type === '') $type = 'LEFT';
				$kw = $typeMap[$type] ?? ($type . ' JOIN');
				$sql .= ' ' . $kw . ' ' . $parseTable($j['table'], false, true);
				if(isset($j['on'])){
					$lim = $srcOf($j['table'])[1];
					$sql .= ' ON ' . $compileOn($j['on'], $lim === null ? null : [$lim, true], $dctx);
				}
				if(isset($j['using'])){
					$using = is_array($j['using']) ? $j['using'] : [$j['using']];
					$sql .= ' USING (' . implode(', ', array_map($id, $using)) . ')';
				}
			}
			return $sql;
		};
		$compileOrder = static function($order, ?array $cctx = null) use ($id, &$val): string{
			$parts = [];
			foreach($order as $k => $v){
				if(is_string($k)) $parts[] = $id($k, $cctx) . ' ' . (is_array($v) ? implode(' ', $v) : $v);
				elseif(is_array($v)) $parts[] = $val($v, false, $cctx);
				else $parts[] = $id($v, $cctx);
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
		$compileSelect = static function(array $qry) use (&$compileFields, &$parseTable, &$compileJoin, &$compileOrder, &$compileLimit, &$cond, $id, $srcOf, $resolveCtx): string{
			$sql = 'SELECT';
			if(!empty($qry['distinct'])) $sql .= ' DISTINCT';
			[$srcs, $multi, $dctx] = $resolveCtx($qry);
			$joins = [];
			foreach($qry['join'] ?? [] as $j){
				if(isset($j['table'])) $joins[] = $j;
			}
			$parts = [];
			if(isset($qry['fields'])) $parts[] = $compileFields($qry['fields'], $dctx);
			foreach($joins as $j){
				if(!isset($j['fields'])) continue;
				$lim = $srcOf($j['table'])[1];
				$parts[] = $compileFields($j['fields'], $lim === null ? null : [$lim, true]);
			}
			$sql .= ' ' . ($parts === [] ? '*' : implode(', ', $parts));
			if(isset($qry['from'])) $sql .= ' ' . $parseTable($qry['from'], true, $multi);
			if($joins) $sql .= $compileJoin($joins, $dctx);
			if(!empty($qry['where'])) $sql .= ' WHERE ' . $cond($qry['where'], $dctx);
			if(!empty($qry['group'])) $sql .= ' GROUP BY ' . implode(', ', array_map(fn($g) => $id($g, $dctx), $qry['group']));
			if(isset($qry['rollup']) && $qry['rollup']) $sql .= ' WITH ROLLUP';
			if(isset($qry['cube']) && $qry['cube']) $sql .= ' WITH CUBE';
			if(!empty($qry['having'])) $sql .= ' HAVING ' . $cond($qry['having'], $dctx);
			if(!empty($qry['order'])) $sql .= ' ' . $compileOrder($qry['order'], $dctx);
			if(isset($qry['limit'])) $sql .= ' ' . $compileLimit($qry['limit'], $qry['offset'] ?? null);
			if(isset($qry['lock'])){
				if(is_array($qry['lock'])){
					if(isset($qry['lock'][0])) $sql .= ' FOR ' . $qry['lock'][0] . (isset($qry['lock'][1]) ? ' ' . $qry['lock'][1] : '');
				}
				else $sql .= ' FOR ' . $qry['lock'];
			}
			return $sql;
		};
		$compileInsert = static function(array $qry) use ($id, &$compile, &$val, &$_cfg): ?string{
			if(!isset($qry['table']) || (!isset($qry['value']) && empty($qry['values']) && !isset($qry['select']))) return null;
			$cmd = !empty($qry['replace']) ? $_cfg['replace'] : 'INSERT';
			$sql = $cmd . ' INTO ' . $id($qry['table']);
			if(isset($qry['select'])) return $sql . ' ' . ($compile($qry['select']) ?? '');
			if(isset($qry['value']) || !empty($qry['values'])){
				$rows = isset($qry['value']) ? [$qry['value']] : $qry['values'];
				$cols = array_keys($rows[0]);
				$rowSql = [];
				foreach($rows as $row) $rowSql[] = '(' . implode(', ', array_map(fn($v) => $val($v, true), array_values($row))) . ')';
				$sql .= ' (' . implode(', ', array_map($id, $cols)) . ') VALUES ' . implode(', ', $rowSql);
			}
			if(isset($qry['update'])){
				$parts = [];
				foreach($qry['update'] as $k => $v) $parts[] = $id($k) . ' = ' . $val($v, true);
				$sql .= ' ' . $_cfg['onConflict'] . ' ' . implode(', ', $parts);
			}
			return $sql;
		};
		$compileUpdate = static function(array $qry) use ($id, $parseTable, &$compileJoin, &$compileOrder, &$compileLimit, &$cond, &$val, $resolveCtx): ?string{
			if(!isset($qry['from']) || !isset($qry['set'])) return null;
			[$srcs, $multi, $dctx] = $resolveCtx($qry);
			$sql = 'UPDATE ' . $parseTable($qry['from'], false, $multi);
			$joins = [];
			foreach($qry['join'] ?? [] as $j){
				if(isset($j['table'])) $joins[] = $j;
			}
			if($joins) $sql .= $compileJoin($joins, $dctx);
			$setParts = [];
			foreach($qry['set'] as $k => $v) $setParts[] = $id($k, $dctx) . ' = ' . $val($v, true, $dctx);
			$sql .= ' SET ' . implode(', ', $setParts);
			if(isset($qry['where']) && !empty($qry['where'])) $sql .= ' WHERE ' . $cond($qry['where'], $dctx);
			if(!empty($qry['order'])) $sql .= ' ' . $compileOrder($qry['order'], $dctx);
			if(isset($qry['limit'])) $sql .= ' ' . $compileLimit($qry['limit']);
			return $sql;
		};
		$compileDelete = static function(array $qry) use ($id, $parseTable, &$compileJoin, &$compileOrder, &$compileLimit, &$cond, $resolveCtx): ?string{
			if(!isset($qry['from'])) return null;
			[$srcs, $multi, $dctx] = $resolveCtx($qry);
			$sql = 'DELETE';
			if(!empty($qry['delete'])){
				$targets = is_array($qry['delete']) ? $qry['delete'] : [$qry['delete']];
				$sql .= ' ' . implode(', ', array_map($id, $targets));
			}
			$sql .= ' ' . $parseTable($qry['from'], true, $multi);
			$joins = [];
			foreach($qry['join'] ?? [] as $j){
				if(isset($j['table'])) $joins[] = $j;
			}
			if($joins) $sql .= $compileJoin($joins, $dctx);
			if(isset($qry['where']) && !empty($qry['where'])) $sql .= ' WHERE ' . $cond($qry['where'], $dctx);
			if(!empty($qry['order'])) $sql .= ' ' . $compileOrder($qry['order'], $dctx);
			if(isset($qry['limit'])) $sql .= ' ' . $compileLimit($qry['limit']);
			return $sql;
		};
		$compileSetOp = static function(array $qry) use (&$compile, &$compileOrder, &$compileLimit): ?string{
			$op = strtoupper($qry[0]);
			$type = isset($qry['type']) && is_string($qry['type']) && $qry['type'] !== '' ? ' ' . strtoupper($qry['type']) : '';
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
		$compile = static function(array $qry) use (&$compileSelect, &$compileInsert, &$compileUpdate, &$compileDelete, &$compileSetOp, &$compileWith): ?string{
			$upper = is_string($qry[0] ?? null) ? strtoupper($qry[0]) : '';
			return match ($upper) {
				'SELECT' => $compileSelect($qry),
				'INSERT' => $compileInsert($qry),
				'UPDATE' => $compileUpdate($qry),
				'DELETE' => $compileDelete($qry),
				'UNION', 'EXCEPT', 'INTERSECT' => $compileSetOp($qry),
				'WITH' => $compileWith($qry),
				default => null,
			};
		};
	}
	$result = $compile($query);
	return $useParam ? [$result, $params] : $result;
}