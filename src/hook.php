<?php
declare(strict_types=1);
namespace ff;
/**
 * 钩子系统，支持注册/触发分离，与容器集成。
 * ```
 * // 注册回调到钩子（请求级，随 container(null) 自动清空）
 * hook('after', fn() => output());
 * hook('end', fn() => test());
 * // 触发钩子
 * hook();                     // 触发 #hook.[] 序列
 * hook('after');              // 触发单个钩子
 * hook(['after', 'end']);     // 按序触发
 * // 清空钩子
 * hook('after', null);        // 清空指定钩子
 * hook(null);                 // 清空所有钩子
 * ```
 * @param string|array|null $event       字符串为钩子名；数组为钩子名列表，按序触发
 * @param callable|array|null $param     字符串时指定回调；null 时清空
 * @return null
 */
function hook(string|array|null $event = null, callable|array|null $param = null): null{
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
		$hooks[] = $param;
		return container("#hook.$event", $hooks);
	}
	if(is_string($event)){
		foreach(container("#hook.$event") ?? [] as $cb) $cb();
	}
	elseif(is_array($event)){
		foreach($event as $name) hook($name);
	}
	return null;
}
