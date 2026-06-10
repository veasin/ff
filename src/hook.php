<?php
namespace nx;
/**
 * 钩子系统，支持注册/触发分离，与容器集成。
 * 0 参触发：hook() 触发默认序列
 * bool 开关：hook(true) 开启钩子模式
 * null 清空：hook(null) 清空所有钩子；hook('name', null) 清空指定钩子
 * ```
 * // 开启内部函数自动注册（持久级，Worker 启动时调用一次）
 * hook(true);                              // 默认序列 ['after', 'end']
 * hook(true, ['after', 'end']);            // 自定义默认序列
 * // 注册回调到钩子（请求级，随 container(null) 自动清空）
 * hook('after', fn() => output());
 * hook('end', fn() => test());
 * // 触发钩子
 * hook();                     // 触发默认序列
 * hook('after');              // 触发单个钩子
 * hook(['after', 'end']);     // 自定义触发顺序
 * // 清空钩子
 * hook('after', null);        // 清空指定钩子
 * hook(null);                 // 清空所有钩子
 * ```
 * @param bool|string|array|null $event true 开启钩子模式；字符串为钩子名；数组为自定义触发序列
 * @param callable|array|null    $param true 时指定默认序列；字符串时指定回调；null 时清空
 * @return null
 */
function hook(bool|string|array|null $event = null, callable|array|null $param = null): null{
	static $hookNames = [];
	if(0 === func_num_args()){
		$defaults = container('^#hook');
		if(is_array($defaults)) foreach($defaults as $name) hook($name);
		return null;
	}
	if(true === $event) return container('^#hook', $param ?? ['after', 'end']);
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
}
