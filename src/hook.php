<?php
namespace nx;
/**
 * 钩子系统，支持注册/触发分离，与容器集成。
 * ```
 * // 开启内部函数自动注册（持久级，Worker 启动时调用一次）
 * hook(true);                              // 默认序列 ['after', 'end']
 * hook(true, ['after', 'end']);            // 自定义默认序列
 *
 * // 注册回调到钩子（请求级，随 container(null) 自动清空）
 * hook('after', fn() => output());
 * hook('end', fn() => test());
 *
 * // 触发钩子
 * hook();                     // 触发默认序列
 * hook('after');              // 触发单个钩子
 * hook(['after', 'end']);     // 自定义触发顺序
 * ```
 * @param bool|string|array|null $event  true 开启钩子模式；字符串为钩子名；数组为自定义触发序列
 * @param callable|array|null    $param  true 时指定默认序列；字符串时指定回调
 * @return void
 */
function hook(bool|string|array|null $event = null, callable|array|null $param = null){
	if(true === $event) return container('^#hook', $param ?? ['after', 'end']);
	if(is_string($event) && $param !== null){
		$hooks = container("#hook.$event") ?? [];
		$hooks[] = $param;
		return container("#hook.$event", $hooks);
	}
	if($event === null && 0 === func_num_args()){
		$defaults = container('^#hook');
		if(is_array($defaults)) foreach($defaults as $name) hook($name);
		return null;
	}
	if(is_string($event)){
		foreach(container("#hook.$event") ?? [] as $cb) $cb();
	}
	elseif(is_array($event)){
		foreach($event as $name) hook($name);
	}
}
