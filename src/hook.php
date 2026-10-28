<?php
declare(strict_types=1);
namespace ff;
/**
 * 钩子系统，支持注册/触发分离，与容器集成。
 * ```
 * // 注册回调到钩子（请求级，随 container(null) 自动清空）
 * hook('after', fn() => output());
 * hook('end', fn() => test());
 * // 带优先级注册——数字越小越先执行
 * hook('after', fn() => ..., -100);
 * hook('after', fn() => ..., 0);
 * hook('after', fn() => ..., 100);
 * // 触发钩子
 * hook();                     // 触发 #hook.[] 序列
 * hook('after');              // 触发单个钩子
 * hook(['after', 'end']);     // 按序触发
 * // 清空钩子
 * hook('after', null);        // 清空指定钩子
 * hook(null);                 // 清空所有钩子
 * ```
 * @param string|array|null $event    字符串为钩子名；数组为钩子名列表，按序触发
 * @param callable|null     $param    回调函数；null 时清空
 * @param int               $priority 优先级（数字越小越先执行，默认 0）
 * @return null
 */
function hook(string|array|null $event = null, callable|null $param = null, int $priority = 0): null{
	static $hookNames = [];
	if(0 === func_num_args()){
		foreach(container('#hook.[]') ?? [] as $name) hook($name);
		return null;
	}
	if(func_num_args() === 1 && $event === null){
		foreach($hookNames as $name) container("#hook.$name", null);
		$hookNames = [];
		return null;
	}
	if(is_string($event) && func_num_args() >= 2 && $param === null){
		container("#hook.$event", null);
		$hookNames = array_values(array_filter($hookNames, fn($n) => $n !== $event));
		return null;
	}
	if(is_string($event) && $param !== null){
		$hookNames[] = $event;
		$hooks = container("#hook.$event") ?? [];
		$hooks[] = [$param, $priority];
		return container("#hook.$event", $hooks);
	}
	if(is_string($event)){
		$hooks = container("#hook.$event") ?? [];
		if(!empty($hooks)){
			$indexedHooks = array_values(array_map(fn($item) => [$item[0], $item[1] ?? 0], $hooks));
			usort($indexedHooks, fn($a, $b) => ($a[1] ?? 0) - ($b[1] ?? 0));
			foreach(array_column($indexedHooks, 0) as $cb){
				if(is_callable($cb)) $cb();
			}
		}
	}
	elseif(is_array($event)) foreach($event as $name) hook($name);
	return null;
}

