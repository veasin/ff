<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{sql, test};

// ===== SELECT — 基础 =====
test('SELECT fields', sql(['SELECT', 'fields' => ['id', 'name'], 'from' => 'user']), 'SELECT `id`, `name` FROM `user`');
test('SELECT wildcard', sql(['SELECT', 'from' => 'user']), 'SELECT * FROM `user`');
test('SELECT no from', sql(['SELECT']), 'SELECT *');
test('SELECT distinct', sql(['SELECT', 'fields' => ['type'], 'from' => 'user', 'distinct' => true]), 'SELECT DISTINCT `type` FROM `user`');
test('SELECT no table', sql(['SELECT', 'fields' => [1, 2, 3]]), 'SELECT 1, 2, 3');
test('SELECT no table literals', sql(['SELECT', 'fields' => [1, true, null]]), 'SELECT 1, TRUE, NULL');
test('SELECT table wildcard', sql(['SELECT', 'fields' => ['user.*']]), 'SELECT `user`.*');
test('SELECT from alias string', sql(['SELECT', 'from' => 'user u']), 'SELECT * FROM `user` `u`');
test('SELECT from alias dotted', sql(['SELECT', 'from' => 'db.user u']), 'SELECT * FROM `db`.`user` `u`');

// ===== SELECT WHERE — 条件 kv =====
test('WHERE eq', sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1]]), 'SELECT * FROM `user` WHERE `id` = 1');
test('WHERE null', sql(['SELECT', 'from' => 'user', 'where' => ['deleted_at' => null]]), 'SELECT * FROM `user` WHERE `deleted_at` IS NULL');
test('WHERE string lit', sql(['SELECT', 'from' => 'user', 'where' => ['name' => 'vea']]), "SELECT * FROM `user` WHERE `name` = 'vea'");
test('WHERE bool', sql(['SELECT', 'from' => 'user', 'where' => ['active' => true]]), 'SELECT * FROM `user` WHERE `active` = TRUE');
test('WHERE AND connector', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'a' => 1, 'b' => 2]]), 'SELECT * FROM `user` WHERE `a` = 1 AND `b` = 2');
test('WHERE OR connector', sql(['SELECT', 'from' => 'user', 'where' => ['OR', 'a' => 1, 'b' => 2]]), 'SELECT * FROM `user` WHERE (`a` = 1 OR `b` = 2)');
test('WHERE implicit AND', sql(['SELECT', 'from' => 'user', 'where' => ['a' => 1, 'b' => 2]]), 'SELECT * FROM `user` WHERE `a` = 1 AND `b` = 2');
test('WHERE nested OR/AND', sql(['SELECT', 'from' => 'user', 'where' => ['OR', 'a' => 0, ['AND', 'b' => 1, 'c' => 1]]]), 'SELECT * FROM `user` WHERE (`a` = 0 OR `b` = 1 AND `c` = 1)');

// ===== SELECT WHERE — 操作符条件 =====
test('WHERE NE', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'type' => ['NE', 0]]]), 'SELECT * FROM `user` WHERE `type` != 0');
test('WHERE GT', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'age' => ['GT', 18]]]), 'SELECT * FROM `user` WHERE `age` > 18');
test('WHERE GTE', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'age' => ['GE', 18]]]), 'SELECT * FROM `user` WHERE `age` >= 18');
test('WHERE LT', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'age' => ['LT', 18]]]), 'SELECT * FROM `user` WHERE `age` < 18');
test('WHERE LTE', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'age' => ['LE', 18]]]), 'SELECT * FROM `user` WHERE `age` <= 18');
test('WHERE LIKE', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'title' => ['LIKE', '%php%']]]), "SELECT * FROM `user` WHERE `title` LIKE '%php%'");
test('WHERE IN list', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'id' => [1, 2, 5]]]), 'SELECT * FROM `user` WHERE `id` IN (1, 2, 5)');
test('WHERE NOT IN', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'status' => ['NOT IN', 0, 1]]]), 'SELECT * FROM `user` WHERE `status` NOT IN (0, 1)');
test('WHERE BETWEEN', sql(['SELECT', 'from' => 'user', 'where' => ['AND', ['BETWEEN', 'age', 18, 60]]]), 'SELECT * FROM `user` WHERE `age` BETWEEN 18 AND 60');

// ===== SELECT WHERE — 表达式条件 =====
test('WHERE expr eq', sql(['SELECT', 'from' => 'user', 'where' => [['eq', 'flag', 1]]]), 'SELECT * FROM `user` WHERE `flag` = 1');
test('WHERE expr ne', sql(['SELECT', 'from' => 'user', 'where' => [['ne', 'type', 0]]]), 'SELECT * FROM `user` WHERE `type` != 0');
test('WHERE expr gt', sql(['SELECT', 'from' => 'user', 'where' => [['gt', 'age', 18]]]), 'SELECT * FROM `user` WHERE `age` > 18');
test('WHERE expr isnull', sql(['SELECT', 'from' => 'user', 'where' => [['isnull', 'deleted_at']]]), 'SELECT * FROM `user` WHERE `deleted_at` IS NULL');
test('WHERE expr is not null', sql(['SELECT', 'from' => 'user', 'where' => [['is not null', 'deleted_at']]]), 'SELECT * FROM `user` WHERE `deleted_at` IS NOT NULL');
test('WHERE expr NOT', sql(['SELECT', 'from' => 'user', 'where' => ['NOT', ['eq', 'flag', 1]]]), 'SELECT * FROM `user` WHERE NOT `flag` = 1');
test('WHERE expr AND', sql(['SELECT', 'from' => 'user', 'where' => [['and', ['eq', 'a', 1], ['eq', 'b', 2]]]]), 'SELECT * FROM `user` WHERE (`a` = 1 AND `b` = 2)');
test('WHERE expr OR', sql(['SELECT', 'from' => 'user', 'where' => [['or', ['eq', 'a', 1], ['eq', 'b', 2]]]]), 'SELECT * FROM `user` WHERE (`a` = 1 OR `b` = 2)');

// ===== SELECT — 函数与表达式 =====
test('expr COUNT', sql(['SELECT', 'fields' => [['COUNT', '*']], 'from' => 'user']), 'SELECT COUNT(*) FROM `user`');
test('expr COUNT alias', sql(['SELECT', 'fields' => ['total' => ['COUNT', '*']], 'from' => 'user']), 'SELECT COUNT(*) `total` FROM `user`');
test('expr IF', sql(['SELECT', 'fields' => ['label' => ['IF', ['gt', 'age', 18], ['s', 'adult'], ['s', 'minor']]], 'from' => 'user']), "SELECT IF(`age` > 18, 'adult', 'minor') `label` FROM `user`");
test('expr NOW', sql(['SELECT', 'fields' => [['NOW']]]), 'SELECT NOW()');
test('expr DATE_FORMAT', sql(['SELECT', 'fields' => [['DATE_FORMAT', 'created_at', '%Y-%m']]]), "SELECT DATE_FORMAT(`created_at`, '%Y-%m')");
test('expr TRIM', sql(['SELECT', 'fields' => [['TRIM', 'name']], 'from' => 'user']), 'SELECT TRIM(`name`) FROM `user`');

// ===== SELECT — 算术表达式 =====
test('expr add', sql(['SELECT', 'fields' => ['total' => ['add', 'price', 'tax']], 'from' => 'order']), 'SELECT `price` + `tax` `total` FROM `order`');
test('expr sub', sql(['SELECT', 'fields' => ['diff' => ['sub', 'income', 'cost']], 'from' => 'account']), 'SELECT `income` - `cost` `diff` FROM `account`');
test('expr mul', sql(['SELECT', 'fields' => ['total' => ['mul', 'price', 'qty']], 'from' => 'order']), 'SELECT `price` * `qty` `total` FROM `order`');
test('expr div', sql(['SELECT', 'fields' => ['avg' => ['div', 'total', 'count']], 'from' => 'stats']), 'SELECT `total` / `count` `avg` FROM `stats`');
test('expr mod', sql(['SELECT', 'fields' => ['rem' => ['mod', 'id', 3]], 'from' => 'user']), 'SELECT `id` % 3 `rem` FROM `user`');

// ===== SELECT — 子查询 =====
test('WHERE EXISTS', sql(['SELECT', 'from' => 'user', 'where' => ['AND', ['EXISTS', ['SELECT', 'fields' => [1], 'from' => 'log', 'where' => [['eq', 'user_id', 'id']]]]]]), 'SELECT * FROM `user` WHERE EXISTS (SELECT 1 FROM `log` WHERE `user_id` = `id`)');
test('WHERE NOT EXISTS', sql(['SELECT', 'from' => 'user', 'where' => ['AND', ['NOT EXISTS', ['SELECT', 'fields' => [1], 'from' => 'log', 'where' => [['eq', 'user_id', 'id']]]]]]), 'SELECT * FROM `user` WHERE NOT EXISTS (SELECT 1 FROM `log` WHERE `user_id` = `id`)');
test('WHERE IN subq', sql(['SELECT', 'from' => 'user', 'where' => ['IN', 'id', ['SELECT', 'fields' => ['user_id'], 'from' => 'log']]]), 'SELECT * FROM `user` WHERE `id` IN (SELECT `user_id` FROM `log`)');
test('WHERE NOT IN subq', sql(['SELECT', 'from' => 'user', 'where' => ['NOT IN', 'id', ['SELECT', 'fields' => ['user_id'], 'from' => 'log']]]), 'SELECT * FROM `user` WHERE `id` NOT IN (SELECT `user_id` FROM `log`)');
test('WHERE = subq', sql(['SELECT', 'from' => 'order', 'where' => [['eq', 'amount', ['SELECT', 'fields' => [['SUM', 'amount']], 'from' => 'order']]]]), 'SELECT * FROM `order` WHERE `amount` = (SELECT SUM(`amount`) FROM `order`)');
test('FROM subquery', sql(['SELECT', 'fields' => ['a.*'], 'from' => ['a' => ['SELECT', 'from' => 'log', 'where' => ['type' => 1]]]]), 'SELECT `a`.* FROM (SELECT * FROM `log` WHERE `type` = 1) `a`');
test('FROM subquery alias string', sql(['SELECT', 'from' => ['a' => ['SELECT', 'from' => 'log']]]), 'SELECT * FROM (SELECT * FROM `log`) `a`');

// ===== SELECT — CASE =====
test('CASE simple', sql(['SELECT', 'fields' => ['label' => ['CASE', 'status', ['WHEN', 1, ['s', 'active']], ['WHEN', 2, ['s', 'inactive']], ['ELSE', ['s', 'unknown']]]], 'from' => 'user']), "SELECT CASE `status` WHEN 1 THEN 'active' WHEN 2 THEN 'inactive' ELSE 'unknown' END `label` FROM `user`");
test('CASE searched', sql(['SELECT', 'fields' => ['label' => ['CASE', ['WHEN', ['gt', 'age', 18], ['s', 'adult']], ['WHEN', ['gt', 'age', 12], ['s', 'teen']], ['ELSE', ['s', 'child']]]], 'from' => 'user']), "SELECT CASE WHEN `age` > 18 THEN 'adult' WHEN `age` > 12 THEN 'teen' ELSE 'child' END `label` FROM `user`");

// ===== SELECT — 窗口函数 =====
test('ROW_NUMBER OVER', sql(['SELECT', 'fields' => ['rn' => ['ROW_NUMBER', 'OVER' => ['PARTITION' => 'dept_id', 'ORDER' => ['salary' => 'DESC']]]], 'from' => 'employee']), 'SELECT ROW_NUMBER() OVER (PARTITION BY `dept_id` ORDER BY `salary` DESC) `rn` FROM `employee`');
test('RANK OVER', sql(['SELECT', 'fields' => ['rk' => ['RANK', 'OVER' => ['ORDER' => ['score' => 'DESC']]]], 'from' => 'score']), 'SELECT RANK() OVER (ORDER BY `score` DESC) `rk` FROM `score`');
test('SUM OVER partition', sql(['SELECT', 'fields' => ['total' => ['SUM', 'amount', 'OVER' => ['PARTITION' => 'dept_id']]], 'from' => 'sale']), 'SELECT SUM(`amount`) OVER (PARTITION BY `dept_id`) `total` FROM `sale`');
test('SUM OVER frame', sql(['SELECT', 'fields' => ['running' => ['SUM', 'amount', 'OVER' => ['PARTITION' => 'dept_id', 'ORDER' => ['date' => 'ASC'], 'frame' => ['ROWS', 'UNBOUNDED PRECEDING', 'AND', 'CURRENT ROW']]]], 'from' => 'sale']), 'SELECT SUM(`amount`) OVER (PARTITION BY `dept_id` ORDER BY `date` ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) `running` FROM `sale`');

// ===== SELECT — JOIN =====
test('JOIN LEFT default', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'u.id']]]]), 'SELECT * FROM `user` `u` LEFT JOIN `info` `i` ON (`user_id` = `u`.`id`)');
test('JOIN INNER', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'u.id'], 'type' => 'INNER']]]), 'SELECT * FROM `user` `u` INNER JOIN `info` `i` ON (`user_id` = `u`.`id`)');
test('JOIN RIGHT', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => ['r' => 'role'], 'on' => ['user_id' => 'u.id'], 'type' => 'RIGHT']]]), 'SELECT * FROM `user` `u` RIGHT JOIN `role` `r` ON (`user_id` = `u`.`id`)');
test('JOIN CROSS', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => 'role', 'type' => 'CROSS']]]), 'SELECT * FROM `user` `u` CROSS JOIN `role`');
test('JOIN NATURAL', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => 'role', 'type' => 'NATURAL']]]), 'SELECT * FROM `user` `u` NATURAL JOIN `role`');
test('JOIN STRAIGHT', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => 'role', 'type' => 'STRAIGHT']]]), 'SELECT * FROM `user` `u` STRAIGHT_JOIN `role`');
test('JOIN USING string', sql(['SELECT', 'from' => 'user', 'join' => [['table' => 'user_role', 'using' => 'user_id']]]), 'SELECT * FROM `user` LEFT JOIN `user_role` USING (`user_id`)');
test('JOIN USING array', sql(['SELECT', 'from' => 'user', 'join' => [['table' => 'user_role', 'using' => ['user_id', 'role_id']]]]), 'SELECT * FROM `user` LEFT JOIN `user_role` USING (`user_id`, `role_id`)');
test('JOIN multiple', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'u.id']], ['table' => ['r' => 'role'], 'on' => ['role_id' => 'u.role_id'], 'type' => 'INNER']]]), 'SELECT * FROM `user` `u` LEFT JOIN `info` `i` ON (`user_id` = `u`.`id`) INNER JOIN `role` `r` ON (`role_id` = `u`.`role_id`)');
test('JOIN subquery', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => ['a' => ['SELECT', 'fields' => ['*'], 'from' => 'log']], 'on' => ['user_id' => 'u.id']]]]), 'SELECT * FROM `user` `u` LEFT JOIN (SELECT * FROM `log`) `a` ON (`user_id` = `u`.`id`)');
test('JOIN ON condition array', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['AND', ['eq', 'user_id', 'u.id']]]]]), 'SELECT * FROM `user` `u` LEFT JOIN `info` `i` ON (`user_id` = `u`.`id`)');
test('JOIN table no alias', sql(['SELECT', 'from' => 'user', 'join' => [['table' => 'role', 'on' => ['role_id' => 'user.role_id']]]]), 'SELECT * FROM `user` LEFT JOIN `role` ON (`role_id` = `user`.`role_id`)');

// ===== SELECT — GROUP BY / HAVING / ORDER / LIMIT =====
test('GROUP BY single', sql(['SELECT', 'fields' => ['type'], 'from' => 'user', 'group' => ['type']]), 'SELECT `type` FROM `user` GROUP BY `type`');
test('GROUP BY multiple', sql(['SELECT', 'fields' => ['region', 'product'], 'from' => 'sales', 'group' => ['region', 'product']]), 'SELECT `region`, `product` FROM `sales` GROUP BY `region`, `product`');
test('GROUP BY ROLLUP', sql(['SELECT', 'fields' => ['region', 'total' => ['SUM', 'amount']], 'from' => 'sales', 'group' => ['region', 'product'], 'rollup' => true]), 'SELECT `region`, SUM(`amount`) `total` FROM `sales` GROUP BY `region`, `product` WITH ROLLUP');
test('GROUP BY CUBE', sql(['SELECT', 'fields' => ['region', 'total' => ['SUM', 'amount']], 'from' => 'sales', 'group' => ['region', 'product'], 'cube' => true]), 'SELECT `region`, SUM(`amount`) `total` FROM `sales` GROUP BY `region`, `product` WITH CUBE');
test('HAVING gt', sql(['SELECT', 'fields' => ['type', 'cnt' => ['COUNT', '*']], 'from' => 'user', 'group' => ['type'], 'having' => ['gt', ['COUNT', '*'], 5]]), 'SELECT `type`, COUNT(*) `cnt` FROM `user` GROUP BY `type` HAVING COUNT(*) > 5');
test('HAVING condition kv', sql(['SELECT', 'fields' => ['type', 'cnt' => ['COUNT', '*']], 'from' => 'user', 'group' => ['type'], 'having' => ['cnt' => ['GT', 5]]]), 'SELECT `type`, COUNT(*) `cnt` FROM `user` GROUP BY `type` HAVING `cnt` > 5');
test('ORDER BY DESC', sql(['SELECT', 'from' => 'user', 'order' => ['created_at' => 'DESC']]), 'SELECT * FROM `user` ORDER BY `created_at` DESC');
test('ORDER BY multiple', sql(['SELECT', 'from' => 'user', 'order' => ['type' => 'ASC', 'created_at' => 'DESC', 'id']]), 'SELECT * FROM `user` ORDER BY `type` ASC, `created_at` DESC, `id`');
test('ORDER BY NULLS LAST', sql(['SELECT', 'from' => 'user', 'order' => ['name' => ['ASC', 'NULLS LAST']]]), 'SELECT * FROM `user` ORDER BY `name` ASC NULLS LAST');
test('ORDER BY expression', sql(['SELECT', 'from' => 'user', 'order' => [['RAND']]]), 'SELECT * FROM `user` ORDER BY RAND()');
test('LIMIT OFFSET', sql(['SELECT', 'from' => 'user', 'limit' => 10, 'offset' => 5]), 'SELECT * FROM `user` LIMIT 10 OFFSET 5');
test('LIMIT array mysql', sql(['SELECT', 'from' => 'user', 'limit' => [10, 20]]), 'SELECT * FROM `user` LIMIT 10, 20');

// ===== SELECT — LOCK =====
test('LOCK UPDATE', sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1], 'lock' => 'UPDATE']), 'SELECT * FROM `user` WHERE `id` = 1 FOR UPDATE');
test('LOCK SHARE', sql(['SELECT', 'from' => 'user', 'lock' => 'SHARE']), 'SELECT * FROM `user` FOR SHARE');
test('LOCK NOWAIT', sql(['SELECT', 'from' => 'user', 'lock' => ['UPDATE', 'NOWAIT']]), 'SELECT * FROM `user` FOR UPDATE NOWAIT');
test('LOCK SKIP LOCKED', sql(['SELECT', 'from' => 'user', 'lock' => ['UPDATE', 'SKIP LOCKED']]), 'SELECT * FROM `user` FOR UPDATE SKIP LOCKED');

// ===== SELECT — RAW =====
test('RAW field', sql(['SELECT', 'fields' => [['RAW', 'NOW()']]]), 'SELECT NOW()');
test('RAW in where', sql(['SELECT', 'from' => 'user', 'where' => [['eq', ['RAW', 'LENGTH(name)'], 5]]]), "SELECT * FROM `user` WHERE LENGTH(name) = 5");

// ===== INSERT =====
test('INSERT single', sql(['INSERT', 'table' => 'user', 'value' => ['name' => 'vea', 'age' => 30]]), "INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30)");
test('INSERT multi', sql(['INSERT', 'table' => 'user', 'values' => [['name' => 'vea', 'age' => 30], ['name' => 'f0', 'age' => 25]]]), "INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30), ('f0', 25)");
test('INSERT bool null', sql(['INSERT', 'table' => 'user', 'value' => ['active' => true, 'deleted_at' => null]]), "INSERT INTO `user` (`active`, `deleted_at`) VALUES (TRUE, NULL)");
test('REPLACE', sql(['INSERT', 'table' => 'user', 'value' => ['id' => 1, 'name' => 'vea'], 'replace' => true]), "REPLACE INTO `user` (`id`, `name`) VALUES (1, 'vea')");
test('INSERT ON DUPLICATE KEY', sql(['INSERT', 'table' => 'user', 'value' => ['id' => 1, 'name' => 'newname'], 'update' => ['name' => 'newname']]), "INSERT INTO `user` (`id`, `name`) VALUES (1, 'newname') ON DUPLICATE KEY UPDATE `name` = 'newname'");
test('INSERT ON DUPLICATE multi', sql(['INSERT', 'table' => 'user', 'value' => ['id' => 1, 'name' => 'newname', 'age' => 25], 'update' => ['name' => 'newname', 'age' => 25]]), "INSERT INTO `user` (`id`, `name`, `age`) VALUES (1, 'newname', 25) ON DUPLICATE KEY UPDATE `name` = 'newname', `age` = 25");
test('INSERT SELECT', sql(['INSERT', 'table' => 'log_archive', 'select' => ['SELECT', 'from' => 'log', 'where' => [['lt', 'created_at', ['s', '2023-01-01']]]]]), "INSERT INTO `log_archive` SELECT * FROM `log` WHERE `created_at` < '2023-01-01'");

// ===== UPDATE =====
test('UPDATE basic', sql(['UPDATE', 'from' => 'user', 'set' => ['name' => 'newname'], 'where' => ['id' => 1]]), "UPDATE `user` SET `name` = 'newname' WHERE `id` = 1");
test('UPDATE alias string', sql(['UPDATE', 'from' => 'user u', 'set' => ['name' => 'newname'], 'where' => ['id' => 1]]), "UPDATE `user` `u` SET `name` = 'newname' WHERE `id` = 1");
test('UPDATE alias array', sql(['UPDATE', 'from' => ['u' => 'user'], 'set' => ['u.name' => 'newname'], 'where' => ['u.id' => 1]]), "UPDATE `user` `u` SET `u`.`name` = 'newname' WHERE `u`.`id` = 1");
test('UPDATE JOIN', sql(['UPDATE', 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'u.id']]], 'set' => ['u.name' => 'i.nickname'], 'where' => ['u.status' => 1]]), "UPDATE `user` `u` LEFT JOIN `info` `i` ON (`user_id` = `u`.`id`) SET `u`.`name` = 'i.nickname' WHERE `u`.`status` = 1");
test('UPDATE ORDER LIMIT', sql(['UPDATE', 'from' => 'user', 'set' => ['status' => 1], 'order' => ['id'], 'limit' => 10]), 'UPDATE `user` SET `status` = 1 ORDER BY `id` LIMIT 10');
test('UPDATE set RAW', sql(['UPDATE', 'from' => 'user', 'set' => ['counter' => ['RAW', 'counter + 1']], 'where' => ['id' => 1]]), "UPDATE `user` SET `counter` = counter + 1 WHERE `id` = 1");

// ===== DELETE =====
test('DELETE basic', sql(['DELETE', 'from' => 'user', 'where' => ['status' => 0]]), 'DELETE FROM `user` WHERE `status` = 0');
test('DELETE alias string', sql(['DELETE', 'from' => 'log l', 'where' => [['lt', 'l.id', 1000]]]), 'DELETE FROM `log` `l` WHERE `l`.`id` < 1000');
test('DELETE JOIN', sql(['DELETE', 'delete' => ['u'], 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'u.id']]], 'where' => ['u.status' => 0, 'i.type' => 1]]), 'DELETE `u` FROM `user` `u` LEFT JOIN `info` `i` ON (`user_id` = `u`.`id`) WHERE `u`.`status` = 0 AND `i`.`type` = 1');
test('DELETE target string', sql(['DELETE', 'delete' => 'u', 'from' => ['u' => 'user'], 'where' => ['u.status' => 0]]), 'DELETE `u` FROM `user` `u` WHERE `u`.`status` = 0');
test('DELETE ORDER LIMIT', sql(['DELETE', 'from' => 'log', 'where' => [['lt', 'id', 1000]], 'order' => ['id'], 'limit' => 100]), 'DELETE FROM `log` WHERE `id` < 1000 ORDER BY `id` LIMIT 100');

// ===== UNION / EXCEPT / INTERSECT =====
test('UNION', sql(['UNION', ['SELECT', 'from' => 'user'], ['SELECT', 'from' => 'history']]), "SELECT * FROM `user`\nUNION\nSELECT * FROM `history`");
test('UNION ALL', sql(['UNION', 'type' => 'ALL', ['SELECT', 'from' => 'user'], ['SELECT', 'from' => 'history']]), "SELECT * FROM `user`\nUNION ALL\nSELECT * FROM `history`");
test('UNION DISTINCT', sql(['UNION', 'type' => 'DISTINCT', ['SELECT', 'from' => 'user'], ['SELECT', 'from' => 'history']]), "SELECT * FROM `user`\nUNION DISTINCT\nSELECT * FROM `history`");
test('UNION ORDER LIMIT', sql(['UNION', 'order' => ['id' => 'DESC'], 'limit' => 10, ['SELECT', 'from' => 'user'], ['SELECT', 'from' => 'history']]), "(\n  SELECT * FROM `user`\n  UNION\n  SELECT * FROM `history`\n)\nORDER BY `id` DESC\nLIMIT 10");
test('UNION three', sql(['UNION', ['SELECT', 'from' => 'a'], ['SELECT', 'from' => 'b'], ['SELECT', 'from' => 'c']]), "SELECT * FROM `a`\nUNION\nSELECT * FROM `b`\nUNION\nSELECT * FROM `c`");
test('EXCEPT', sql(['EXCEPT', ['SELECT', 'from' => 'user'], ['SELECT', 'from' => 'history']]), "SELECT * FROM `user`\nEXCEPT\nSELECT * FROM `history`");
test('INTERSECT', sql(['INTERSECT', ['SELECT', 'from' => 'user'], ['SELECT', 'from' => 'history']]), "SELECT * FROM `user`\nINTERSECT\nSELECT * FROM `history`");

// ===== WITH (CTE) =====
test('WITH', sql(['WITH', 'sales' => ['SELECT', 'fields' => ['region', ['SUM', 'amount']], 'from' => 'orders', 'group' => ['region']], ['SELECT', 'fields' => ['region', 'sales'], 'from' => 'sales', 'where' => [['gt', 'sales', 1000]]]]), "WITH\n`sales` AS (\nSELECT `region`, SUM(`amount`) FROM `orders` GROUP BY `region`\n)\nSELECT `region`, `sales` FROM `sales` WHERE `sales` > 1000");
test('WITH RECURSIVE', sql(['WITH', 'recursive' => true, 'tree' => ['SELECT', 'fields' => ['id', 'name'], 'from' => 'user', 'where' => ['parent_id' => 0]], ['SELECT', 'from' => 'tree']]), "WITH RECURSIVE\n`tree` AS (\nSELECT `id`, `name` FROM `user` WHERE `parent_id` = 0\n)\nSELECT * FROM `tree`");

// ===== 字面量 =====
test('lit string marker', sql(['SELECT', 'fields' => [['s', 'hello']]]), "SELECT 'hello'");
test('lit number', sql(['SELECT', 'fields' => [42]]), 'SELECT 42');
test('lit bool', sql(['SELECT', 'fields' => [true]]), 'SELECT TRUE');
test('lit null', sql(['SELECT', 'fields' => [null]]), 'SELECT NULL');

// ===== SQLite 方言差异 =====
test('sqlite REPLACE', sql(['INSERT', 'table' => 'user', 'value' => ['id' => 1, 'name' => 'vea'], 'replace' => true], 'sqlite'), "INSERT OR REPLACE INTO `user` (`id`, `name`) VALUES (1, 'vea')");
test('sqlite ON CONFLICT', sql(['INSERT', 'table' => 'user', 'value' => ['id' => 1, 'name' => 'newname'], 'update' => ['name' => 'newname']], 'sqlite'), "INSERT INTO `user` (`id`, `name`) VALUES (1, 'newname') ON CONFLICT DO UPDATE SET `name` = 'newname'");
test('sqlite LIMIT array', sql(['SELECT', 'from' => 'user', 'limit' => [10, 20]], 'sqlite'), 'SELECT * FROM `user` LIMIT 20 OFFSET 10');
test('sqlite no LOCK', sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1], 'lock' => 'UPDATE'], 'sqlite'), 'SELECT * FROM `user` WHERE `id` = 1');
test('sqlite no LOCK array', sql(['SELECT', 'from' => 'user', 'lock' => ['UPDATE', 'NOWAIT']], 'sqlite'), 'SELECT * FROM `user`');
test('sqlite no STRAIGHT_JOIN', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => 'role', 'type' => 'STRAIGHT']]], 'sqlite'), 'SELECT * FROM `user` `u` LEFT JOIN `role`');
test('sqlite no CUBE', sql(['SELECT', 'fields' => ['region'], 'from' => 'sales', 'group' => ['region'], 'cube' => true], 'sqlite'), 'SELECT `region` FROM `sales` GROUP BY `region`');

// ===== 异常配置 =====
test('invalid empty array', sql([]), null);
test('invalid unknown type', sql(['FETCH', 'from' => 'user']), null);
test('INSERT no table', sql(['INSERT']), null);
test('INSERT no values', sql(['INSERT', 'table' => 'user']), null);
test('UNION no subqueries', sql(['UNION']), null);
test('WITH no CTEs', sql(['WITH']), null);
test('UPDATE no from', sql(['UPDATE']), null);
test('UPDATE no set', sql(['UPDATE', 'from' => 'user']), null);
test('DELETE no from', sql(['DELETE']), null);
test('empty fields fallback', sql(['SELECT', 'fields' => [], 'from' => 'user']), 'SELECT * FROM `user`');
test('empty where', sql(['SELECT', 'from' => 'user', 'where' => []]), 'SELECT * FROM `user`');
test('empty order', sql(['SELECT', 'from' => 'user', 'order' => []]), 'SELECT * FROM `user`');
test('empty group', sql(['SELECT', 'from' => 'user', 'group' => []]), 'SELECT * FROM `user`');
test('empty join', sql(['SELECT', 'from' => 'user', 'join' => []]), 'SELECT * FROM `user`');
test('join no table', sql(['SELECT', 'from' => 'user', 'join' => [[]]]), 'SELECT * FROM `user`');
test('empty values in IN', sql(['SELECT', 'from' => 'user', 'where' => ['id' => []]]), 'SELECT * FROM `user` WHERE `id` IN ()');

// ===== PARAM 模式 =====
test('param WHERE eq', sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1]], ['param' => true]), ['SELECT * FROM `user` WHERE `id` = ?', [1]]);
test('param WHERE multiple', sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1, 'name' => 'vea']], ['param' => true]), ["SELECT * FROM `user` WHERE `id` = ? AND `name` = ?", [1, 'vea']]);
test('param INSERT', sql(['INSERT', 'table' => 'user', 'value' => ['name' => 'vea', 'age' => 30]], ['param' => true]), ["INSERT INTO `user` (`name`, `age`) VALUES (?, ?)", ['vea', 30]]);
test('param UPDATE', sql(['UPDATE', 'from' => 'user', 'set' => ['name' => 'newname'], 'where' => ['id' => 1]], ['param' => true]), ["UPDATE `user` SET `name` = ? WHERE `id` = ?", ['newname', 1]]);
test('param WHERE IN list', sql(['SELECT', 'from' => 'user', 'where' => ['AND', 'id' => [1, 2, 5]]], ['param' => true]), ['SELECT * FROM `user` WHERE `id` IN (?, ?, ?)', [1, 2, 5]]);
test('param NULL stays NULL', sql(['SELECT', 'from' => 'user', 'where' => ['deleted_at' => null]], ['param' => true]), ['SELECT * FROM `user` WHERE `deleted_at` IS NULL', []]);
test('param bool as int', sql(['SELECT', 'from' => 'user', 'where' => ['active' => true]], ['param' => true]), ['SELECT * FROM `user` WHERE `active` = ?', [1]]);
test('param INSERT multi', sql(['INSERT', 'table' => 'user', 'values' => [['name' => 'vea', 'age' => 30], ['name' => 'f0', 'age' => 25]]], ['param' => true]), ["INSERT INTO `user` (`name`, `age`) VALUES (?, ?), (?, ?)", ['vea', 30, 'f0', 25]]);
test('param ON DUPLICATE KEY', sql(['INSERT', 'table' => 'user', 'value' => ['id' => 1, 'name' => 'newname'], 'update' => ['name' => 'newname']], ['param' => true]), ["INSERT INTO `user` (`id`, `name`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `name` = ?", [1, 'newname', 'newname']]);
test('param multi JOIN', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [
	['table' => ['i' => 'info'], 'on' => ['user_id' => 'u.id']],
	['table' => ['r' => 'role'], 'on' => ['role_id' => 'u.role_id'], 'type' => 'INNER'],
], 'where' => ['u.status' => 1, 'u.name' => 'vea']], ['param' => true]), ['SELECT * FROM `user` `u` LEFT JOIN `info` `i` ON (`user_id` = `u`.`id`) INNER JOIN `role` `r` ON (`role_id` = `u`.`role_id`) WHERE `u`.`status` = ? AND `u`.`name` = ?', [1, 'vea']]);
test('param subquery JOIN with WHERE', sql(['SELECT', 'from' => ['u' => 'user'], 'join' => [
	['table' => ['a' => ['SELECT', 'from' => 'log', 'where' => ['type' => 1]]], 'on' => ['user_id' => 'u.id']],
], 'where' => ['u.name' => 'vea']], ['param' => true]), ['SELECT * FROM `user` `u` LEFT JOIN (SELECT * FROM `log` WHERE `type` = ?) `a` ON (`user_id` = `u`.`id`) WHERE `u`.`name` = ?', [1, 'vea']]);

test();
