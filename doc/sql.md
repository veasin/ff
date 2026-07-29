# sql() — $query 数组参数说明

```php
sql(array $query, string|array $options = []): string|array|null
```

`$query` 为数组描述的 SQL 语句，首元素指定操作类型（`SELECT` / `INSERT` / `UPDATE` / `DELETE` / `UNION` / `EXCEPT` / `INTERSECT` / `WITH`），其余命名参数为子句。

`$options`：
- **string**：直接作为方言名，如 `'sqlite'`
- **array**：支持 `'dialect'`（`'mysql'` | `'sqlite'`，默认 `'mysql'`）、`'param'`（`true` 启用 `?` 占位模式，返回 `[$sql, $params]`）

---

## 一、规则

**`[operator, arg1, arg2, ...]`**

第 0 元素为操作符或函数名，后续为参数。

| 参数类型 | 解释 |
|----------|------|
| 数字 / bool / null | 字面量，直接输出 |
| 合法标识符字符串 | 字段名，输出 `` `name` `` |
| 非法标识符字符串 | 字面量，输出 `'text'` |
| `['s', 'text']` | 字面量字符串标记，输出 `'text'` |
| `'*'` / `'table.*'` | 通配符，输出 `*` / `` `table`.* `` |
| `[op, ...]` | 子表达式 / 子查询，递归 |

合法标识符规则：`/^[a-zA-Z_$][a-zA-Z0-9_$]*(?:\.[a-zA-Z_$][a-zA-Z0-9_$]*){0,2}$/`

**字符串值的上下文行为：**

| 上下文 | `string` 含义 |
|--------|--------------|
| 表达式数组 `[op, arg, ...]` | 字段引用（`` `name` ``） |
| 条件 kv / SET / INSERT value | 字面量（`'name'`） |
| ON kv | 字段引用（连接表的字段 = 主表字段） |

| 字符串 | 输出 |
|--------|------|
| `'name'` | `` `name` `` |
| `'user.id'` | `` `user`.`id` ``（`.` 前为表别名） |
| `'%vea%'` | `'%vea%'`（非法标识符，自动降级） |
| `'active'` | `` `active` ``（合法标识符，需字面量时用 `['s', 'active']`） |
| `'*'` | `*`（通配符） |
| `'user.*'` | `` `user`.* ``（表限定通配符） |

---

## 二、表达式

### 字面量

```
123           →  123
true          →  TRUE
null          →  NULL
['s', 'text'] →  'text'
```

### 字段

```
'name'       →  `name`
'u.id'       →  `u`.`id`
```

### 函数

```
['COUNT', '*']             →  COUNT(*)
['IF', cond, a, b]         →  IF(cond, a, b)
['TRIM', a]                →  TRIM(a)
['NOW']                    →  NOW()
['DATE_FORMAT', a, '%Y-%m']  →  DATE_FORMAT(a, '%Y-%m-%d')
```

### 操作符

```
['gt', a, 18]              →  a > 18
['eq', a, 1]               →  a = 1
['ne', a, 0]               →  a != 0
['lt', a, 1]               →  a < 1
['le', a, 1]               →  a <= 1
['ge', a, 18]              →  a >= 18
['LIKE', a, '%x%']         →  a LIKE '%x%'
['IN', f, 1, 2]            →  f IN (1, 2)
['BETWEEN', f, 1, 10]      →  f BETWEEN 1 AND 10
['NOT IN', f, 1, 2]        →  f NOT IN (1, 2)
['isnull', a]              →  a IS NULL
['is not null', a]         →  a IS NOT NULL
['add', a, 1]              →  a + 1
['sub', a, 1]              →  a - 1
['mul', a, 2]              →  a * 2
['div', a, 2]              →  a / 2
['mod', a, 2]              →  a % 2
['and', a, b]              →  (a AND b)
['or', a, b]               →  (a OR b)
```

操作符名规则：小写操作符（`gt`、`eq`、`like`）转 SQL 操作符；大写/其他作为 SQL 函数名。

### 一元操作符

```
['NOT', ['eq', 'a', 1]]  →  NOT (`a` = 1)
['NOT', 'flag']          →  NOT `flag`
```

### N 元逻辑

```
['OR', a, b, c]             →  (a OR b OR c)
['AND', a, b, c, d]         →  (a AND b AND c AND d)
['OR', ['AND', 1, 2], 3]    →  ((1 AND 2) OR 3)
```

### CASE WHEN

```
['CASE', 'status',
    ['WHEN', 1, ['s', 'active']],
    ['WHEN', 2, ['s', 'inactive']],
    ['ELSE', ['s', 'unknown']],
]
→  CASE `status` WHEN 1 THEN 'active' WHEN 2 THEN 'inactive' ELSE 'unknown' END

['CASE',
    ['WHEN', ['gt', 'age', 18], ['s', 'adult']],
    ['WHEN', ['gt', 'age', 12], ['s', 'teen']],
    ['ELSE', ['s', 'child']],
]
→  CASE WHEN `age` > 18 THEN 'adult' WHEN `age` > 12 THEN 'teen' ELSE 'child' END
```

### 窗口函数

```
['ROW_NUMBER', 'OVER' => ['PARTITION' => 'dept_id', 'ORDER' => ['salary' => 'DESC']]]
→  ROW_NUMBER() OVER (PARTITION BY `dept_id` ORDER BY `salary` DESC)

['RANK', 'OVER' => ['ORDER' => ['score' => 'DESC']]]
→  RANK() OVER (ORDER BY `score` DESC)

['SUM', 'amount', 'OVER' => ['PARTITION' => 'dept_id']]
→  SUM(`amount`) OVER (PARTITION BY `dept_id`)
```

**窗口帧（frame）：**

```
['SUM', 'amount', 'OVER' => [
    'PARTITION' => 'dept_id',
    'ORDER'     => ['date' => 'ASC'],
    'frame'     => ['ROWS', 'UNBOUNDED PRECEDING', 'AND', 'CURRENT ROW'],
]]
→  SUM(`amount`) OVER (
      PARTITION BY `dept_id`
      ORDER BY `date` ASC
      ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
    )

['AVG', 'score', 'OVER' => [
    'PARTITION' => 'class_id',
    'frame'     => ['RANGE', '5 PRECEDING', 'AND', '10 FOLLOWING'],
]]
→  AVG(`score`) OVER (
      PARTITION BY `class_id`
      RANGE BETWEEN 5 PRECEDING AND 10 FOLLOWING
    )
```

`frame` 数组元素用空格拼接，自动插入 `BETWEEN`。

### RAW 逃生口

需要直接注入原生 SQL 时使用 `['RAW', 'sql']`：

```
['RAW', 'NOW()']         →  NOW()
['RAW', 'DEFAULT']       →  DEFAULT
['RAW', 'col + 1']       →  col + 1
```

RAW 内容原样输出，不转义、不加引号。

### 子查询

子查询以 `['SELECT', ...]` 形式直接嵌入表达式位置。

```
['IN', 'id', ['SELECT', 'fields' => ['id'], 'from' => 'log']]
→  `id` IN (SELECT `id` FROM `log`)

['EXISTS', ['SELECT', 'fields' => [1], 'from' => 'log', 'where' => ['user_id' => 'id']]]
→  EXISTS (SELECT 1 FROM `log` WHERE `user_id` = `id`)

['NOT EXISTS', ['SELECT', 'fields' => [1], 'from' => 'log']]
→  NOT EXISTS (SELECT 1 FROM `log`)

['=', 'amount', ['SELECT', 'fields' => [['SUM', 'amount']], 'from' => 'orders']]
→  `amount` = (SELECT SUM(`amount`) FROM `orders`)
```

---

## 三、字段列表

作为独立语法单元，供查询引用。

| 形式 | 输出 |
|------|------|
| `'id'` | `` `id` `` |
| `'u.name'` | `` `u`.`name` `` |
| `['COUNT', '*']` | `COUNT(*)` |
| `'total' => ['COUNT', '*']` | `COUNT(*)` `` `total` `` |
| `'label' => ['IF', ['gt', 'age', 18], ['s', 'adult'], ['s', 'minor']]` | `IF(`age` > 18, 'adult', 'minor')` `` `label` `` |
| `['id', 'name']` | `` `id`, `name` `` |

`key => value` 表示 AS 别名。

---

## 四、条件

作为独立语法单元，供 WHERE、HAVING、ON 等引用。

### 结构

`[connector, condition, condition, ...]`。connector 可缺省，缺省为 `AND`。

```
[AND, [...], [...], ...]
[OR,  [...], [...], ...]
```

每个 condition：

| 形式 | 说明 |
|------|------|
| `'field' => scalar` | 字段比较（等值/IS NULL） |
| `'field' => [op, ...]` | 字段操作（简写） |
| `[op, ...]` | 独立表达式 |

```
'status' => 1               等价于 ['eq', 'status', 1]
'name' => ['LIKE', '%p%']   等价于 ['LIKE', 'name', '%p%']
```

**条件 kv 简写中，`string` 值视为字面量**（与表达式数组中的行为不同——那里 string 是字段引用）：

```
'name' => 'vea'              →  `name` = 'vea'           （lit，字面量）
'name' => ['s', 'vea']      →  `name` = 'vea'           （同上，冗余安全）
'on'  => {user_id: 'id'}    →  `i`.`user_id` = `u`.`id`  （ON 中 string=字段引用）
```

### key-value 分支

```
'field' => scalar             →  `field` = scalar
'field' => null               →  `field` IS NULL         （特判）
'field' => [非字符串首, ...]  →  `field` IN (...)         （首元素非 string）
'field' => [字符串操作符, ...] →  `field` op(...)          （首元素为已知操作符）
```

`null` 在条件 kv 中自动转为 `IS NULL`（而非 `= NULL`）。`['NOT', null]` 转为 `IS NOT NULL`。

### 示例

```
['AND',
    'status' => 1,
    'type'   => ['NE', 0],
    'title'  => ['LIKE', '%php%'],
    'tag_id' => [1, 2, 5],
    ['OR',
        ['eq', 'flag', 1],
        ['isnull', 'deleted_at'],
    ],
]

→  `status` = 1 AND `type` != 0 AND `title` LIKE '%php%'
   AND `tag_id` IN (1, 2, 5) AND (`flag` = 1 OR `deleted_at` IS NULL)
```

### 连接器

```
['AND', 'a' => 1, 'b' => 2]    →  `a` = 1 AND `b` = 2
['OR',  'a' => 1, 'b' => 2]    →  (`a` = 1 OR `b` = 2)
['a' => 1, 'b' => 2]            →  `a` = 1 AND `b` = 2    (缺省 AND)
```

---

## 五、from

from 描述数据来源，支持以下形式：

| 形式 | 说明 | 示例输出 |
|------|------|---------|
| `'user'` | 单表无别名 | `` FROM `user` `` |
| `['user', 'role']` | 多表无别名 | `` FROM `user`, `role` `` |
| `{u: 'user'}` | 单/多表有别名 | `` FROM `user` `u` `` |
| `{u: 'user', r: 'role'}` | 多表有别名 | `` FROM `user` `u`, `role` `r` `` |
| `{a: ['SELECT', ...]}` | 子查询 | `` FROM (SELECT ...) `a` `` |

迭代逻辑（PHP 伪代码）：

```
for (from as alias => name)
    if alias is string
        if name is array → 子查询，别名为 alias
        else → 表 name，别名为 alias
    else
        // alias 为数字
        → 表 name，无别名
```

---

## 六、查询

所有查询 op 统一使用命名参数。

### 6.1 SELECT

```
['SELECT',
    'fields' => ['id', 'name', 'total' => ['COUNT', '*']],
    'from'   => {u: 'user'},
    'join'   => [
        {table: {i: 'info'}, on: {user_id: 'id'}},
        {table: {p: 'profile'}, on: {user_id: 'id'}, type: 'INNER'},
    ],
    'where'  => ['AND', 'status' => 1, ['OR', ['eq', 'flag', 1], ['isnull', 'deleted_at']]],
    'group'  => ['type'],
    'having' => ['AND', ['gt', ['COUNT', '*'], 5]],
    'order'  => ['created_at' => 'DESC'],
    'limit'  => 20,
    'offset' => 0,
    'distinct' => true,
]

→  SELECT DISTINCT `id`, `name`, COUNT(*) `total`
   FROM `user` `u`
   LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
   INNER JOIN `profile` `p` ON (`p`.`user_id` = `u`.`id`)
   WHERE `status` = 1 AND (`flag` = 1 OR `deleted_at` IS NULL)
   GROUP BY `type`
   HAVING COUNT(*) > 5
   ORDER BY `created_at` DESC
   LIMIT 20 OFFSET 0
```

**子查询在 from：**

```
['SELECT',
    'fields' => ['a.*'],
    'from'   => {a: ['SELECT', 'fields' => ['*'], 'from' => 'log', 'where' => ['type' => 1]]},
]

→  SELECT `a`.* FROM (SELECT * FROM `log` WHERE `type` = 1) `a`
```

**多表无别名：**

```
['SELECT',
    'fields' => ['user.id', 'role.name'],
    'from'   => ['user', 'role'],
    'where'  => [['eq', 'user.role_id', 'role.id']],
]

→  SELECT `user`.`id`, `role`.`name` FROM `user`, `role` WHERE `user`.`role_id` = `role`.`id`
```

**无 table 的 SELECT：**

```
['SELECT', 'fields' => [1, 2, 3]]
→  SELECT 1, 2, 3
```

**行锁：**

```
['SELECT', 'fields' => ['*'], 'from' => 'user', 'where' => ['id' => 1], 'lock' => 'UPDATE']
→  SELECT * FROM `user` WHERE `id` = 1 FOR UPDATE

['SELECT', 'fields' => ['*'], 'from' => 'user', 'lock' => 'SHARE']
→  SELECT * FROM `user` FOR SHARE

['SELECT', 'fields' => ['*'], 'from' => 'user', 'lock' => ['UPDATE', 'NOWAIT']]
→  SELECT * FROM `user` FOR UPDATE NOWAIT

['SELECT', 'fields' => ['*'], 'from' => 'user', 'lock' => ['UPDATE', 'SKIP LOCKED']]
→  SELECT * FROM `user` FOR UPDATE SKIP LOCKED
```

`lock` 为字符串时直接输出 `FOR {lock}`，为数组时拼接 `FOR {[0]} {[1]}`。

### 排序、分组与分页

**ORDER BY：** 每项为字段名（默认 ASC）或 `'field' => 'DIR'` 对。

```
'order' => ['created_at' => 'DESC', 'id']           →  ORDER BY `created_at` DESC, `id`
'order' => ['created_at' => ['DESC', 'NULLS LAST']] →  ORDER BY `created_at` DESC NULLS LAST
'order' => [['RAND']]                                →  ORDER BY RAND()
```

数组项分类：字符串键→字段+方向；数字键+字符串→字段 ASC；数字键+数组→表达式。

**GROUP BY：**

```
'group' => ['region', 'product']            →  GROUP BY `region`, `product`
'group' => ['region', 'product'],
'rollup' => true                            →  GROUP BY `region`, `product` WITH ROLLUP
'cube' => true                              →  GROUP BY `region`, `product` WITH CUBE
```

**LIMIT：**

```
'limit' => 20, 'offset' => 10       →  LIMIT 20 OFFSET 10          （标准）
'limit' => [10, 20]                 →  LIMIT 10, 20                （MySQL 双参数：offset, count）
```

`limit` 为数组时输出 `LIMIT {[0]}, {[1]}`（MySQL 方言）。

### 6.2 INSERT

```
['INSERT',
    'table' => 'user',
    'value' => ['name' => 'vea', 'age' => 30],
]

→  INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30)

['INSERT',
    'table'  => 'user',
    'values' => [
        ['name' => 'vea', 'age' => 30],
        ['name' => 'f0', 'age' => 25],
    ],
]

→  INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30), ('f0', 25)
```

**REPLACE：**

```
['INSERT',
    'table'   => 'user',
    'value'   => ['id' => 1, 'name' => 'vea'],
    'replace' => true,
]

→  REPLACE INTO `user` (`id`, `name`) VALUES (1, 'vea')
```

**ON DUPLICATE KEY UPDATE：**

```
['INSERT',
    'table'  => 'user',
    'value'  => ['id' => 1, 'name' => 'newname'],
    'update' => ['name' => 'newname'],
]

→  INSERT INTO `user` (`id`, `name`) VALUES (1, 'newname')
   ON DUPLICATE KEY UPDATE `name` = 'newname'
```

**INSERT ... SELECT：**

`'select'` 与 `'value'` / `'values'` 互斥。

```
['INSERT',
    'table'  => 'log_archive',
    'select' => ['SELECT',
        'fields' => ['*'],
        'from'   => 'log',
        'where'  => [['lt', 'created_at', ['s', '2023-01-01']]],
    ],
]

→  INSERT INTO `log_archive`
   SELECT * FROM `log` WHERE `created_at` < '2023-01-01'
```

### 6.3 UPDATE

```
['UPDATE',
    'from'   => {u: 'user'},
    'set'    => ['name' => 'newname', 'updated_at' => ['s', '2024-01-01']],
    'where'  => ['id' => 1],
]

→  UPDATE `user` `u` SET `name` = 'newname', `updated_at` = '2024-01-01' WHERE `id` = 1
```

**多表 UPDATE：**

```
['UPDATE',
    'from'   => {u: 'user'},
    'join'   => [{table: {i: 'info'}, on: {user_id: 'id'}}],
    'set'    => {'u.name': ['i', 'nickname']},
    'where'  => ['u.status' => 1],
]

→  UPDATE `user` `u`
   LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
   SET `u`.`name` = `i`.`nickname`
   WHERE `u`.`status` = 1
```

UPDATE 也支持 `order` / `limit`：

```
['UPDATE', 'from' => 'user', 'set' => ['status' => 1], 'order' => ['id'], 'limit' => 10]
→  UPDATE `user` SET `status` = 1 ORDER BY `id` LIMIT 10
```

### 6.4 DELETE

```
['DELETE',
    'from'  => 'user',
    'where' => ['status' => 0],
]

→  DELETE FROM `user` WHERE `status` = 0
```

**多表 DELETE：**

```
['DELETE',
    'delete' => ['u'],
    'from'   => {u: 'user'},
    'join'   => [{table: {i: 'info'}, on: {user_id: 'id'}}],
    'where'  => ['u.status' => 0, 'i.type' => 1],
]

→  DELETE `u` FROM `user` `u`
   LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
   WHERE `u`.`status` = 0 AND `i`.`type` = 1
```

DELETE 也支持 `order` / `limit`：

```
['DELETE', 'from' => 'log', 'where' => [['lt', 'id', 1000]], 'order' => ['id'], 'limit' => 100]
→  DELETE FROM `log` WHERE `id` < 1000 ORDER BY `id` LIMIT 100
```

### 6.5 UNION / EXCEPT / INTERSECT

```
['UNION',
    ['SELECT', 'fields' => ['id'], 'from' => 'user'],
    ['SELECT', 'fields' => ['id'], 'from' => 'history'],
]

→  SELECT `id` FROM `user` UNION SELECT `id` FROM `history`

['UNION',
    'type' => 'ALL',
    ['SELECT', 'fields' => ['id'], 'from' => 'user'],
    ['SELECT', 'fields' => ['id'], 'from' => 'history'],
]

→  SELECT `id` FROM `user` UNION ALL SELECT `id` FROM `history`

['EXCEPT',
    ['SELECT', 'fields' => ['id'], 'from' => 'user'],
    ['SELECT', 'fields' => ['id'], 'from' => 'history'],
]

['INTERSECT',
    ['SELECT', 'fields' => ['id'], 'from' => 'user'],
    ['SELECT', 'fields' => ['id'], 'from' => 'history'],
]
```

`type`：缺省空（UNION DISTINCT），可选 `'ALL'`、`'DISTINCT'`。

**外层 ORDER BY / LIMIT：**

```
['UNION',
    'order' => ['id' => 'DESC'],
    'limit' => 10,
    ['SELECT', 'fields' => ['id'], 'from' => 'user'],
    ['SELECT', 'fields' => ['id'], 'from' => 'history'],
]

→  (SELECT `id` FROM `user` UNION SELECT `id` FROM `history`)
    ORDER BY `id` DESC LIMIT 10
```

`order` / `limit` / `offset` 作为命名参数出现在 UNION 数组中时，作用于整个联合结果。

### 6.6 WITH（CTE）

```
['WITH',
    'sales' => ['SELECT',
        'fields' => ['region', ['SUM', 'amount']],
        'from'   => 'orders',
        'group'  => ['region'],
    ],
    ['SELECT',
        'fields' => ['region', 'sales'],
        'from'   => 'sales',
        'where'  => [['gt', 'sales', 1000]],
    ],
]

→  WITH `sales` AS (
       SELECT `region`, SUM(`amount`) FROM `orders` GROUP BY `region`
   )
   SELECT `region`, `sales` FROM `sales` WHERE `sales` > 1000
```

CTE 定义以 key-value 形式排列，字符串 key 为 CTE 名。末项（数字 key）为主查询。

**WITH RECURSIVE：**

```
['WITH',
    'recursive' => true,
    'tree' => ['SELECT', 'fields' => ['id', 'name'], 'from' => 'user', 'where' => ['parent_id' => 0]],
    ['SELECT', 'fields' => ['*'], 'from' => 'tree'],
]

→  WITH RECURSIVE `tree` AS (SELECT `id`, `name` FROM `user` WHERE `parent_id` = 0)
    SELECT * FROM `tree`
```

`'recursive' => true` 在 WITH 和 CTE 名之间插入 `RECURSIVE`。

---

## 七、JOIN

`table` 格式与 from 相同。

```
join: [
    {table: {i: 'info'},       on: {user_id: 'id'},                           type: 'LEFT'},
    {table: {p: 'profile'},    on: {user_id: 'id'},                           type: 'INNER'},
    {table: {a: ['SELECT', 'fields' => ['*'], 'from' => 'log']},              on: {user_id: 'id'}},
    {table: 'role',            on: {id: 'role_id'}},
]
```

`type` 缺省 `LEFT`。⚠ `LEFT` 是破坏性缺省值——忘写 type 可能取到预期之外的数据，生产代码建议显式指定 type。
`on` 使用条件语法（参见条件章节）。`on` 与 `using` 互斥。

ON 中 key-value 的 key 为 join 表的字段，value 为主表（或其他已声明的表）的字段：

```
{table: {i: 'info'}, on: {user_id: 'id'}}
→  LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)

// 等效独立表达式写法：
{table: {i: 'info'}, on: ['AND', ['eq', 'user_id', 'id']]}
```

**USING：**（与 `on` 互斥）

```
{table: 'user_role', using: 'user_id'}
→  LEFT JOIN `user_role` USING (`user_id`)

{table: 'user_role', using: ['user_id', 'role_id']}
→  LEFT JOIN `user_role` USING (`user_id`, `role_id`)
```

**其他 JOIN 类型：** `type` 支持值：

| type | 输出 |
|------|------|
| `'LEFT'`（缺省） | `LEFT JOIN` |
| `'INNER'` | `INNER JOIN` |
| `'RIGHT'` | `RIGHT JOIN` |
| `'CROSS'` | `CROSS JOIN` |
| `'NATURAL'` | `NATURAL JOIN` |
| `'STRAIGHT'` | `STRAIGHT_JOIN`（MySQL） |

---

## 八、完整示例

```
['WITH',
    'recent' => ['SELECT',
        'fields' => ['id', 'title', 'created_at'],
        'from'   => 'article',
        'where'  => [['gt', 'created_at', ['s', '2024-01-01']]],
    ],
    ['SELECT',
        'fields' => ['id', 'title', 'label' => ['IF', ['gt', 'view_count', 1000], ['s', 'hot'], ['s', 'normal']]],
        'from'   => {a: ['SELECT',
            'fields' => ['a.id', 'a.title', 'cnt' => ['COUNT', 'c.id']],
            'from'   => {a: 'recent'},
            'join'   => [{table: {c: 'comment'}, on: {article_id: 'a.id'}, type: 'LEFT'}],
            'group'  => ['a.id'],
        ]},
        'where'  => ['AND', 'status' => 1],
        'order'  => ['created_at' => 'DESC'],
        'limit'  => 10,
    ],
]

→  WITH `recent` AS (
       SELECT `id`, `title`, `created_at` FROM `article` WHERE `created_at` > '2024-01-01'
   )
   SELECT `id`, `title`, IF(`view_count` > 1000, 'hot', 'normal') `label`
   FROM (
       SELECT `a`.`id`, `a`.`title`, COUNT(`c`.`id`) `cnt`
       FROM `recent` `a`
       LEFT JOIN `comment` `c` ON (`c`.`article_id` = `a`.`id`)
       GROUP BY `a`.`id`
   ) `a`
   WHERE `status` = 1
   ORDER BY `created_at` DESC
   LIMIT 10
```
