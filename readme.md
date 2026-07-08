# ff: A Minimal, Declarative Functional Framework

`ff` 可以有很多解读：

- **Function Framework** — 函数即框架，一切皆函数
- **Functional Flow** — 函数式数据流，管道组合编排一切
- **Function First** — 函数至上，零类架构
- **Fast & Focused** — 极简专注，不拖泥带水
- **Form & Function** — 形神兼备，设计与功能一体

---

`ff` 是一个轻量级的、纯函数驱动的 PHP 微框架，专为 PHP 8.4+ 设计。核心设计理念：**一函数多用、函数即模块、容器即状态、组合即流程**。

无类、无依赖注入、无服务提供者、无注解路由——全部由命名空间函数组成，极致精简。

### 核心哲学

- **零类架构**：全部使用命名空间函数，无 class、无 DI 容器、无服务提供者
- **一函数多用**：同一函数通过参数个数、类型、值实现不同语义
- **容器即状态**：`container()` 是唯一全局状态管理器，支持请求级/持久级双生命周期
- **组合即流程**：`middleware()` 洋葱模型、`hump()` 链式调用、`cache()` 多级回退，函数组合编排一切
- **失败返回 null**：统一错误语义，管道中自然短路穿透
- **扩展走容器**：所有可扩展能力通过 `container()` 注入，不增加函数参数签名

### 安装

```bash
composer require veasin/ff
```

### 文档

- [函数参考](doc/functions.md) — 所有内置函数的详细说明与示例
- [预制中间件](doc/middlewares.md) — 所有内置中间件的详细说明与示例
- [使用思路与最佳实践](doc/usage.md) — 框架使用思路与最佳实践指南

### 生态

ff 生态下的扩展项目：

| 项目 | 说明 |
|------|------|
| [ff-franken](https://github.com/veasin/ff-franken) | FrankenPHP worker 模式适配器 |
| [ff-llm](https://github.com/veasin/ff-llm) | LLM 统一聊天接口，多 provider 切换、Tool Calling |
| [ff-log-ws](https://github.com/veasin/ff-log-ws) | WebSocket 日志实时传输 |
| [ff-sql](https://github.com/veasin/ff-sql) | SQL 查询构建器、DDL 生成与数据库迁移 |
| [ff-redis](https://github.com/veasin/ff-redis) | Redis 连接池、队列驱动与缓存驱动 |
| [ff-xdebug](https://github.com/veasin/ff-xdebug) | Xdebug 追踪查看器，格式化表格展示调用时间线 |
