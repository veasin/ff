<?php
namespace nx;
/**
 * 执行中间件列表，同时支持洋葱模型和链式调用，不支持阻断。
 * >--^--^--^--> 每个 ^ 都是一次 next()
 * 参数可以是：
 * - 闭包：签名 function($next, ...$args)，调用 $next(...) 会执行下一个中间件。
 * - 文件路径：文件内可通过 $next 变量调用下一个中间件，并通过 return 返回值。
 * - 其他值：自动转换为 function($next, ...$args) { return $next($value, ...$args); }。
 * 执行规则：
 * - 函数通过调用 $next 实现嵌套包裹；不调用 $next 则执行完后自动继续链式调用下一个（如果有），
 *   并将当前返回值作为参数传递给下一个。
 * - 返回值取自最后一个执行的函数（即最终结果），若无显式 return 则返回 null。
 * @param mixed ...$fns 中间件列表
 * @return mixed 最终结果
 */
function hump(mixed ...$fns): mixed{
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
	$execute = function(array $args, bool $inNested) use (&$execute, &$queue): mixed{
		$current = array_shift($queue);
		$result = $current(fn(...$nextArgs) => $execute($nextArgs, true), ...$args);
		if($inNested) return $result;
		return empty($queue) ? $result : $execute([$result], false);
	};
	return $execute([], false);
}