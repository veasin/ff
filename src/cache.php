<?php
declare(strict_types=1);
namespace nx;

use function nx\middleware;

/**
 * 多级缓存函数
 * 接收中间件工厂或闭包，按顺序链式执行。
 * 返回 null 的中间件自动穿透到下一层（?? $next() 回退）。
 * 使用方式:
 * ```
 * cache(apcu('x', middleware: ['ttl' => 3600]), fn($next) => render());
 * cache(apcu('x', middleware: true), 'fallback');
 * cache(fn($next) => 'value');
 * ```
 * @param mixed ...$fns 中间件列表
 * @return mixed
 */
function cache(mixed ...$fns): mixed{
	if(empty($fns)) return null;
	return middleware(...array_map(fn($fn) => match (true) {
		is_callable($fn) => fn($next, ...$params) => $fn($next, ...$params) ?? $next(...$params),
		default => fn($next, ...$params) => $fn,
	}, $fns));
}
