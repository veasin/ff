<?php
declare(strict_types=1);
namespace nx;
/**
 * 执行中间件列表，严格遵循中间件模式（洋葱模型）。
 * 参数可以是：
 * - 闭包：签名 function($next, ...$fns)，调用 $next(...) 会执行下一个中间件。
 * - 文件路径：文件内可通过 $next 变量调用下一个中间件，并通过 return 返回值。
 * - 其他值：自动转换为 function($next, ...$fns) { return $next($value, ...$fns); }，用于传递初始值。
 * 执行规则：
 * - 中间件按顺序执行，只有当前中间件调用了 $next 才会继续执行下一个。
 * - 如果某个中间件未调用 $next，则执行终止，并返回该中间件的返回值。
 * - 返回值取自最后一个执行的中间件的返回值（若无显式 return 则返回 null）。
 * - 允许中间件多次调用 $next，但第一次调用后后续调用将直接返回第一次的结果（防止重复执行）。
 * @param mixed ...$fns 中间件列表（可包含初始值）
 * @return mixed 最终结果
 */
function middleware(mixed ...$fns): mixed{
	if(empty($fns)) return null;
	static $resolve = fn($fn) => match (true) {
		is_string($fn) && is_file($fn) => function($next, ...$args) use ($fn){
			extract(['next' => $next, 'args' => $args], EXTR_SKIP);
			return require $fn;
		},
		is_callable($fn) => $fn,
		default => fn($next, ...$args) => $next($fn, ...$args),
	};
	$queue = array_map($resolve, $fns);
	$execute = null;
	$execute = function(array $args) use (&$execute, &$queue): mixed{
		if(empty($queue)) return $args[0] ?? null;
		$current = array_shift($queue);
		$called = false;
		$nextResult = null;
		$next = function(...$nextArgs) use (&$execute, &$queue, &$called, &$nextResult){
			if($called) return $nextResult;
			$called = true;
			$nextResult = $execute($nextArgs);
			return $nextResult;
		};
		return $current($next, ...$args);
	};
	return $execute([]);
}