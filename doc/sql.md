# sql() — $query 数组参数说明

```php
sql(array $query, string|array $options = []): string|array|null
```

`$query` 为数组描述的 SQL 语句，首元素指定操作类型（`SELECT` / `INSERT` / `UPDATE` / `DELETE` / `UNION` / `EXCEPT` / `INTERSECT` / `WITH`），其余命名参数为子句。

`$options`：
- **string**：直接作为方言名，如 `'sqlite'`
- **array**：支持 `'dialect'`（`'mysql'` | `'sqlite'`，默认 `'mysql'`）、`'param'`（`true` 启用 `?` 占位模式，返回 `[$sql, $params]`）

**方言配置：** 各方言行为差异项通过容器 `#sql.{方言名}` 注册覆盖（`bootstrap.php` 的 `^#sql` 段），未注册项取默认：

| 配置项 | 默认 | 说明 |
|--------|------|------|
| `'limitArray'` | `'comma'` | 数组 limit 输出：`'comma'` → `LIMIT 偏移, 数量`（MySQL）；`'standard'` → `LIMIT 数量 OFFSET 偏移` |
| `'replace'` | `'REPLACE'` | `replace => true` 的写入语法 |
| `'onConflict'` | `'ON DUPLICATE KEY UPDATE'` | `update` 的冲突更新子句 |
| `'boolLiteral'` | `true` | bool 输出 `TRUE`/`FALSE`（`false` 时输出 `1`/`0`） |

配置只翻译「同一语义的方言写法差异」（如 `REPLACE` 与 `INSERT OR REPLACE`）。目标库不支持的特性（如 SQLite 无行锁、无 `STRAIGHT_JOIN`）不在配置范围——编译器原样输出，执行报错由使用方处理。

---

## 一、规则

**`[operator, arg1, arg2, ...]`**

第 0 元素为操作符或函数名，后续为参数。

| 参数类型 | 解释 |
|----------|------|
| 数字 / bool / null | 字面量，直接输出 |
| 裸字符串 | 字段/表名（标识符），输出 `` `name` `` |
| 引号标记字符串 | 字符串字面量，输出 `'text'` |
| 反引号标记字符串 | RAW 透传，原样输出 |
| `'*'` / `'table.*'` | 通配符，输出 `*` / `` `table`.* `` |
| `[op, ...]` | 子表达式 / 子查询，递归 |

**核心原则：SQL 同构，位置无关**

带引号的是字符串值，裸写的是标识符（字段/表名）——与 SQL 源码一致，与所处位置（条件值、SET、INSERT 值、表达式参数）无关。标识符是否有效不由编译器判定，交由 db 验证（如 Unknown column / 表不存在）：

**编译器职责：** sql() 是数组结构到 SQL 文本的翻译器，只按规则转换。缺省值有默认处理（join type 缺省 LEFT、fields 缺省 `*`）；用户明确写入的内容只要符合规范格式一律原样翻译——不降级、不丢弃、不静默替换。写错名字、用了目标库不支持的特性，由 db 执行时报错定位。

| 字符串 | 判定 | 输出 |
|--------|------|------|
| `'name'` | 字段 | `` `name` `` |
| `'user.id'` | 字段（`.` 前为表别名） | `` `user`.`id` `` |
| `"'vea'"` | 字符串值 `vea` | `'vea'` |
| `"'%php%'"` | 字符串值 `%php%` | `'%php%'` |
| `` '`col + 1`' `` | RAW `col + 1` | `col + 1` |
| `'*'` | 通配符 | `*` |
| `'%php%'` | 字段 `%php%`（若 db 报 Unknown column，说明本意是字符串值，应写 `"'%php%'"`） | `` `%php%` `` |

**标记判定与剥离：**

- 长度 ≥ 2，且首位与末位为同种 `'` 或 `"` → 字符串字面量（两种引号等价）
- 长度 ≥ 2，且首位与末位为 `` ` `` → RAW
- 剥离只去第一位与最后一位各一个字符，中间全部为内容：

| PHP 值 | 判定 | 剥离后内容 | 输出 |
|--------|------|-----------|------|
| `"'123'"` | 字面量 | `123` | `'123'` |
| `"''123''"` | 字面量 | `'123'` | `'''123'''`（内容整体转义） |
| `"'it's'"` | 字面量 | `it's` | `'it''s'` |
| `` '`NOW()`' `` | RAW | `NOW()` | `NOW()` |

- 字面量内容中的 `'` 转义为 `''`；标识符内部的反引号双写（`'a`b'` → `` `a``b` ``）
- 标识符含 `.` 按点分段包裹（`'a.b'` → `` `a`.`b` ``），需要含点的整体名请用 RAW
- 标识符位（AS 别名、列名、表名等）收到引号标记字符串时按字面量原样输出，不参数化、不做标识符包裹

**书写 helper（项目级惯例）：**

```php
function s(string $str): string{ return "'{$str}'"; }
```

字面量标记可在项目内封装 helper 统一书写，构造遵守 `标记 + 内容 + 标记`，运行时值同样适用：

```php
['title' => s('vea')]           ≡  ['title' => "'vea'"]
['LIKE', 'title', s($kw)]       // 运行时值，param 模式下内容入 params
```

**大小写：** 关键字识别不区分大小写（输出归一大写）；函数名保留书写原样。

### 表上下文与字段限定

每个字段位持有当前表：

| 位置 | 当前表 |
|------|--------|
| 主查询 fields / where / having / order / group / set | 默认表（from 第一个源） |
| join 项 fields / on 的 key | join 表 |
| on 的 value | 默认表 |
| 子查询内部 | 递归隔离——用自身 from 的第一个源 |

裸标识符按当前表补限定名（别名优先，无别名用表名）；已含 `.` 的跳过（视为显式限定）。该规则递归作用于表达式内部（如 `['COUNT', 'id']`）。

**多源补全 / 单源退化：**

| 源数量 | 行为 |
|--------|------|
| 多源（存在 join 或 from 多表） | 裸字段补表限定：`'id'` → `` `u`.`id` `` |
| 单源 | 不补全；显式前缀与该源表名/别名匹配则退化（`'u.id'` → `` `id` ``、`'u.*'` → `*`），不匹配保持原样交 db 验证；FROM 的别名一并退化 |

```
// 多源：fields、where、on 均按当前表补全
['SELECT', 'from' => ['u' => 'user'], 'join' => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'id']]]]
// 'fields' => ['id', 'nickname']  →  SELECT `u`.`id`, `i`.`nickname` ...

// 单源：不补全，显式限定与该源匹配则退化
['SELECT', 'fields' => ['u.id', 'name'], 'from' => ['u' => 'user']]
→  SELECT `id`, `name` FROM `user`

['SELECT', 'fields' => ['u.*'], 'from' => ['u' => 'user']]
→  SELECT * FROM `user`
```

补全是按当前表的推断：链式 join 的 ON 引用**前面** join 的表时，裸写会被错补默认表前缀，须显式限定——错误由 db 报错定位。

**通配符 `*`：**

| 形态 | 表归属 | 多源 | 单源 |
|------|--------|------|------|
| `'*'` | 无（语句级） | `*` | `*` |
| `'t.*'` | 显式 | 保持 | 前缀匹配该源则退化 `*` |
| `fields => []` | 编译器补 | 该源 `表.*` | 同左后退化 |

`*` 不是字段、无表归属，永不参与补全与退化——`['COUNT', '*']` → `COUNT(*)` 在任何源上下文一致，非特例。

### param 模式

`'param' => true` 时值位置输出 `?` 占位并收集参数，返回 `[$sql, $params]`：

| 内容 | param 模式行为 |
|------|---------------|
| 条件值 / INSERT value / UPDATE set 值 | 绑定 `?`，入 params |
| 引号标记字面量 | 绑定 `?`，标记内容入 params |
| bool | 绑定为 `1` / `0` |
| null | 输出 `NULL`，不绑定 |
| 裸标识符 / RAW | 不绑定，原样输出 |

```php
sql(['SELECT', 'from' => 'user', 'where' => ['id' => 1, 'name' => "'vea'"]], ['param' => true]);
// ['SELECT * FROM `user` WHERE `id` = ? AND `name` = ?', [1, 'vea']]
```

---

## 二、表达式

### 字面量

```
123            →  123
true           →  TRUE
null           →  NULL
"'text'"       →  'text'
```

### 字段

```
'name'       →  `name`
'u.id'       →  `u`.`id`
```

### 函数

```
['COUNT', '*']                  →  COUNT(*)
['IF', cond, a, b]              →  IF(cond, a, b)
['TRIM', a]                     →  TRIM(a)
['NOW']                         →  NOW()
['DATE_FORMAT', a, "'%Y-%m'"]   →  DATE_FORMAT(a, '%Y-%m')
```

### 操作符

二元操作符直接写符号：

```
['=', a, 1]       →  a = 1
['!=', a, 0]      →  a != 0
['<>', a, 0]      →  a <> 0
['<', a, 1]       →  a < 1
['<=', a, 1]      →  a <= 1
['>', a, 18]      →  a > 18
['>=', a, 18]     →  a >= 18
['+', a, 1]       →  a + 1
['-', a, 1]       →  a - 1
['*', a, 2]       →  a * 2
['/', a, 2]       →  a / 2
['%', a, 2]       →  a % 2
```

`!=` 与 `<>` 语义等价，按 SQL 方言原样输出。

关键字操作符：

```
['LIKE', a, "'%x%'"]     →  a LIKE '%x%'
['IN', f, 1, 2]          →  f IN (1, 2)
['NOT IN', f, 1, 2]      →  f NOT IN (1, 2)
['BETWEEN', f, 1, 10]    →  f BETWEEN 1 AND 10
['EXISTS', subq]         →  EXISTS (subq)
['NOT EXISTS', subq]     →  NOT EXISTS (subq)
['IS NULL', a]           →  a IS NULL
['IS NOT NULL', a]       →  a IS NOT NULL
['AND', a, b]            →  (a AND b)
['OR', a, b]             →  (a OR b)
['NOT', a]               →  NOT a
```

首元素分派：符号 → 二元操作符；命中关键字表 → 对应语法；其余标识符 → 函数调用。

### 一元操作符

```
['NOT', ['=', 'a', 1]]  →  NOT (`a` = 1)
['NOT', 'flag']         →  NOT `flag`
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
    ['WHEN', 1, "'active'"],
    ['WHEN', 2, "'inactive'"],
    ['ELSE', "'unknown'"],
]
→  CASE `status` WHEN 1 THEN 'active' WHEN 2 THEN 'inactive' ELSE 'unknown' END

['CASE',
    ['WHEN', ['>', 'age', 18], "'adult'"],
    ['WHEN', ['>', 'age', 12], "'teen'"],
    ['ELSE', "'child'"],
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

需要直接注入原生 SQL 时用反引号包裹：

```
'`NOW()`'      →  NOW()
'`DEFAULT`'    →  DEFAULT
'`col + 1`'    →  col + 1
```

RAW 内容原样输出，不转义、不加引号、不参数化。

### 子查询

子查询以 `['SELECT', ...]` 形式直接嵌入表达式位置。

```
['IN', 'id', ['SELECT', 'fields' => ['id'], 'from' => 'log']]
→  `id` IN (SELECT `id` FROM `log`)

['EXISTS', ['SELECT', 'fields' => [1], 'from' => 'log', 'where' => [['=', 'user_id', 'id']]]]
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
| `'label' => ['IF', ['>', 'age', 18], "'adult'", "'minor'"]` | `IF(`age` > 18, 'adult', 'minor')` `` `label` `` |
| `['id', 'name']` | `` `id`, `name` `` |

`key => value` 表示 AS 别名。

上表为字段形态的解析输出；字段位处于表上下文时按限定规则补全或退化（见一、规则·表上下文与字段限定）。

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
| `'field' => scalar` | 等值（null 特判 IS NULL） |
| `'field' => "'str'"` | 字符串值等值 |
| `'field' => 'field2'` | 字段对字段比较（裸 = 字段） |
| `'field' => [op, ...]` | 字段操作（简写） |
| `'field' => [1, 2, 5]` | IN |
| `[op, ...]` | 独立表达式 |

```
'status' => 1                    等价于 ['=', 'status', 1]
'name' => ['LIKE', "'%p%'"]      等价于 ['LIKE', 'name', "'%p%'"]
```

字符串值与字段的区分不依赖位置：

```
'name' => "'vea'"      →  `name` = 'vea'        （字符串值）
'name' => 'vea'        →  `name` = `vea`         （字段比较）
'name' => 'creator'    →  `name` = `creator`     （字段比较）
```

### key-value 分支

```
'field' => 数字/bool/null     →  `field` = scalar
'field' => null              →  `field` IS NULL        （特判）
'field' => "'str'"           →  `field` = 'str'
'field' => 'field2'          →  `field` = `field2`
'field' => ['>', 18]         →  `field` > 18           （简写）
'field' => [1, 2, 5]         →  `field` IN (1, 2, 5)   （首元素非字符串）
```

`null` 在条件 kv 中自动转为 `IS NULL`（而非 `= NULL`）。

### 示例

```
['AND',
    'status' => 1,
    'type'   => ['!=', 0],
    'title'  => ['LIKE', "'%php%'"],
    'tag_id' => [1, 2, 5],
    ['OR',
        ['=', 'flag', 1],
        ['IS NULL', 'deleted_at'],
    ],
]

→  `status` = 1 AND `type` != 0 AND `title` LIKE '%php%'
   AND `tag_id` IN (1, 2, 5) AND (`flag` = 1 OR `deleted_at` IS NULL)
```

### 连接器

```
['AND', 'a' => 1, 'b' => 2]    →  `a` = 1 AND `b` = 2
['OR',  'a' => 1, 'b' => 2]    →  (`a` = 1 OR `b` = 2)
['a' => 1, 'b' => 2]           →  `a` = 1 AND `b` = 2    (缺省 AND)
```

---

## 五、from

from 描述数据来源，表名裸写（同 SQL），支持以下形式：

| 形式 | 说明 | 示例输出 |
|------|------|---------|
| `'user'` | 单表无别名 | `` FROM `user` `` |
| `'user u'` | 单表带别名 | `` FROM `user` `u` `` |
| `['user', 'role']` | 多表无别名 | `` FROM `user`, `role` `` |
| `['u' => 'user']` | 单/多表有别名 | `` FROM `user` `u` `` |
| `['a' => 'user u']` | key 别名权威 | `` FROM `user` `a` `` |
| `['u' => 'user', 'r' => 'role']` | 多表有别名 | `` FROM `user` `u`, `role` `r` `` |
| `['a' => ['SELECT', ...]]` | 子查询 | `` FROM (SELECT ...) `a` `` |

迭代逻辑（PHP 伪代码）：

```
for (from as alias => name)
    if alias is string
        if name is array → 子查询，别名为 alias
        else → 表 name 解析（含尾部别名），别名为 alias
    else
        // alias 为数字
        → 表 name，尾部别名生效
```

key 为别名、value 为表实体（表名或子查询）——与字段 AS 别名（`'total' => ['COUNT', '*']`）方向一致。

**key 别名权威**：value 为字符串时解析其尾部别名（`'posts p'` → 表 `posts` + 别名 `p`）；key 已占据别名槽位时，尾部别名被抛弃——`['a' => 'posts p']` → `` `posts` `a` ``。反引号 RAW 标记的 value（`` '`posts p`' ``）整体不拆，原样输出交 db 验证。

多表 from 的裸字段按默认表（第一个源，无别名用表名）补全；需要其他表的字段显式限定。

---

## 六、查询

所有查询 op 统一使用命名参数。

### 6.1 SELECT

```
['SELECT',
    'fields' => ['id', 'name', 'total' => ['COUNT', '*']],
    'from'   => ['u' => 'user'],
    'join'   => [
        ['table' => ['i' => 'info'], 'on' => ['user_id' => 'id']],
        ['table' => ['p' => 'profile'], 'on' => ['user_id' => 'id'], 'type' => 'INNER'],
    ],
    'where'  => ['AND', 'status' => 1, ['OR', ['=', 'flag', 1], ['IS NULL', 'deleted_at']]],
    'group'  => ['type'],
    'having' => ['AND', ['>', ['COUNT', '*'], 5]],
    'order'  => ['created_at' => 'DESC'],
    'limit'  => 20,
    'offset' => 0,
    'distinct' => true,
]

→  SELECT DISTINCT `u`.`id`, `u`.`name`, COUNT(*) `total`
   FROM `user` `u`
   LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
   INNER JOIN `profile` `p` ON (`p`.`user_id` = `u`.`id`)
   WHERE `u`.`status` = 1 AND (`u`.`flag` = 1 OR `u`.`deleted_at` IS NULL)
   GROUP BY `u`.`type`
   HAVING COUNT(*) > 5
   ORDER BY `u`.`created_at` DESC
   LIMIT 20 OFFSET 0
```

### select 空间

fields 描述各数据源的字段贡献，主表与各 join 表依次并入同一空间（源顺序 = from 顺序 + join 声明顺序）：

| 源的 fields | 贡献 |
|------|------|
| 缺省（不写键） | 无贡献 |
| `[]` | 该源 `表.*` |
| `['id', ...]` | 按该源上下文补全限定后的字段（join 源用 join 表） |

空间为空（主表与全部 join 均缺省）→ 编译器输出 `*`。

```
主表 ['id'] + join ['nickname']        →  SELECT `u`.`id`, `i`.`nickname` FROM ...
主表 [] + join []                      →  SELECT `u`.*, `i`.* FROM ...
主表缺省 + join ['nickname']           →  SELECT `i`.`nickname` FROM ...
主表缺省 + join 缺省                   →  SELECT * FROM ...
```

join 项携带 fields 的完整示例：

```
['SELECT',
    'fields' => ['id'],
    'from'   => ['u' => 'user'],
    'join'   => [
        ['table' => ['i' => 'info'], 'on' => ['user_id' => 'id'], 'fields' => ['nickname']],
    ],
]

→  SELECT `u`.`id`, `i`.`nickname`
   FROM `user` `u` LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
```

UPDATE / DELETE 的 join 忽略 fields（无 select 空间）。

**子查询在 from：**

```
['SELECT',
    'fields' => ['a.*'],
    'from'   => ['a' => ['SELECT', 'fields' => ['*'], 'from' => 'log', 'where' => ['type' => 1]]],
]

→  SELECT * FROM (SELECT * FROM `log` WHERE `type` = 1)
```

from 唯一源为子查询且无 join → 单源：`a.*` 前缀匹配别名退化、FROM 别名一并退化。子查询内部递归隔离，用自身上下文（`log` 单源，字段保持裸写）。

**多表无别名：**

```
['SELECT',
    'fields' => ['user.id', 'role.name'],
    'from'   => ['user', 'role'],
    'where'  => [['=', 'user.role_id', 'role.id']],
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

主表键与其他语句统一为 `from`。INSERT 无别名投影位：`from` 的别名（尾部写法与 key 写法）被丢弃；多源 from 原样输出，语法错误由 db 报出。

```
['INSERT',
    'from' => 'user',
    'value' => ['name' => "'vea'", 'age' => 30],
]

→  INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30)

['INSERT',
    'from'   => 'user',
    'values' => [
        ['name' => "'vea'", 'age' => 30],
        ['name' => "'f0'", 'age' => 25],
    ],
]

→  INSERT INTO `user` (`name`, `age`) VALUES ('vea', 30), ('f0', 25)

// 别名丢弃
'from' => 'user u'          →  INSERT INTO `user` ...
'from' => ['u' => 'user']   →  INSERT INTO `user` ...
'from' => ['a' => 'user u'] →  INSERT INTO `user` ...

// 多源原样输出（交 db 报错）
'from' => ['user', 'log']   →  INSERT INTO `user`, `log` ...
```

**REPLACE：**

```
['INSERT',
    'from'    => 'user',
    'value'   => ['id' => 1, 'name' => "'vea'"],
    'replace' => true,
]

→  REPLACE INTO `user` (`id`, `name`) VALUES (1, 'vea')
```

**ON DUPLICATE KEY UPDATE：**

```
['INSERT',
    'from'   => 'user',
    'value'  => ['id' => 1, 'name' => "'newname'"],
    'update' => ['name' => "'newname'"],
]

→  INSERT INTO `user` (`id`, `name`) VALUES (1, 'newname')
   ON DUPLICATE KEY UPDATE `name` = 'newname'
```

**INSERT ... SELECT：**

`'select'` 与 `'value'` / `'values'` 互斥。

```
['INSERT',
    'from'   => 'log_archive',
    'select' => ['SELECT',
        'fields' => ['*'],
        'from'   => 'log',
        'where'  => [['<', 'created_at', "'2023-01-01'"]],
    ],
]

→  INSERT INTO `log_archive`
   SELECT * FROM `log` WHERE `created_at` < '2023-01-01'
```

### 6.3 UPDATE

```
['UPDATE',
    'from'   => ['u' => 'user'],
    'set'    => ['name' => "'newname'", 'updated_at' => "'2024-01-01'"],
    'where'  => ['id' => 1],
]

→  UPDATE `user` SET `name` = 'newname', `updated_at` = '2024-01-01' WHERE `id` = 1
```

单源（无 join）：FROM 别名退化，字段保持裸写。

**多表 UPDATE：**

```
['UPDATE',
    'from'   => ['u' => 'user'],
    'join'   => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'id']]],
    'set'    => ['u.name' => 'i.nickname'],
    'where'  => ['u.status' => 1],
]

→  UPDATE `user` `u`
   LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
   SET `u`.`name` = `i`.`nickname`
   WHERE `u`.`status` = 1
```

SET 值裸写即为字段引用（同 SQL：`SET u.name = i.nickname`）。

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
    'from'   => ['u' => 'user'],
    'join'   => [['table' => ['i' => 'info'], 'on' => ['user_id' => 'id']]],
    'where'  => ['u.status' => 0, 'i.type' => 1],
]

→  DELETE `u` FROM `user` `u`
   LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
   WHERE `u`.`status` = 0 AND `i`.`type` = 1
```

DELETE 也支持 `order` / `limit`：

```
['DELETE', 'from' => 'log', 'where' => [['<', 'id', 1000]], 'order' => ['id'], 'limit' => 100]
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
        'where'  => [['>', 'sales', 1000]],
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

`table` 格式与 from 相同（含 key 别名权威规则：`['a' => 'posts p']` → `` LEFT JOIN `posts` `a` ``）。

```
join: [
    ['table' => ['i' => 'info'],    'on' => ['user_id' => 'id'],    'type' => 'LEFT'],
    ['table' => ['p' => 'profile'], 'on' => ['user_id' => 'id'],    'type' => 'INNER'],
    ['table' => ['a' => ['SELECT', 'fields' => ['*'], 'from' => 'log']], 'on' => ['user_id' => 'id']],
    ['table' => 'role',             'on' => ['id' => 'role_id']],
]
```

`type` 缺省 `LEFT`。⚠ `LEFT` 是破坏性缺省值——忘写 type 可能取到预期之外的数据，生产代码建议显式指定 type。
`on` 使用条件语法（参见条件章节）。`on` 与 `using` 互斥。

**ON 的表上下文（分侧补全）：** key 补 join 表前缀，value 补默认表（from 第一个源）前缀；含 `.` 的显式限定跳过：

```
['table' => ['i' => 'info'], 'on' => ['user_id' => 'id']]
→  LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)

// 显式限定等效：
['table' => ['i' => 'info'], 'on' => ['user_id' => 'u.id']]
→  同上

// 独立表达式写法（字段同样按 ON 上下文补全）：
['table' => ['i' => 'info'], 'on' => ['AND', ['=', 'user_id', 'id']]]
```

链式 join 的 ON 引用**前面** join 的表时，裸写会被错补默认表前缀，须显式限定：

```
['table' => ['r' => 'role'], 'on' => ['id' => 'i.role_id']]
→  LEFT JOIN `role` `r` ON (`r`.`id` = `i`.`role_id`)
```

**join fields：** join 项可携带 `fields`，字段补 join 表限定并依次并入主查询的 select 空间（见 6.1·select 空间）：

```
['SELECT',
    'from'   => ['u' => 'user'],
    'join'   => [
        ['table' => ['i' => 'info'], 'on' => ['user_id' => 'id'], 'fields' => ['nickname']],
        ['table' => ['r' => 'role'], 'on' => ['id' => 'role_id'], 'fields' => []],
    ],
]

→  SELECT `i`.`nickname`, `r`.*
   FROM `user` `u`
   LEFT JOIN `info` `i` ON (`i`.`user_id` = `u`.`id`)
   LEFT JOIN `role` `r` ON (`r`.`id` = `u`.`role_id`)
```

主表 fields 缺省 → 无贡献，空间只含 join 贡献。`fields => []` 表示该源 `表.*`。

UPDATE / DELETE 的 join 忽略 fields。

**USING：**（与 `on` 互斥）

```
['table' => 'user_role', 'using' => 'user_id']
→  LEFT JOIN `user_role` USING (`user_id`)

['table' => 'user_role', 'using' => ['user_id', 'role_id']]
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
        'where'  => [['>', 'created_at', "'2024-01-01'"]],
    ],
    ['SELECT',
        'fields' => ['id', 'title', 'label' => ['IF', ['>', 'view_count', 1000], "'hot'", "'normal'"]],
        'from'   => ['a' => ['SELECT',
            'fields' => ['a.id', 'a.title', 'cnt' => ['COUNT', 'c.id']],
            'from'   => ['a' => 'recent'],
            'join'   => [['table' => ['c' => 'comment'], 'on' => ['article_id' => 'a.id'], 'type' => 'LEFT']],
            'group'  => ['a.id'],
        ]],
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
   )
   WHERE `status` = 1
   ORDER BY `created_at` DESC
   LIMIT 10
```

外层 from 唯一源为子查询且无 join → 单源：FROM 别名 `a` 退化，字段裸写不补全。内层子查询存在 join → 多源：FROM 别名保留、ON 与显式限定字段按表上下文补全。
