<?php
declare(strict_types=1);
namespace ff;
/**
 * 启用扩展能力，核心不内置任何 feature。
 * 扩展包通过 container('^#ext.enable.{feature}', fn() => ...) 注册 handler，同一 feature 在进程内只执行一次。
 * ```
 * enable('cli');                  // 启用 CLI 输入/输出/路由
 * enable('cli-test');             // 启用 CLI 测试渲染
 * enable('cli', 'cli-test');      // 同时启用多个能力
 * ```
 * @param string ...$features 能力名称，小写字母 + 连字符
 * @return null
 */
function enable(string ...$features): null{
	static $enabled = [];
	$entry = container('^#ext.enable') ?? [];
	foreach($features as $feature){
		if(isset($enabled[$feature])) continue;
		if(!is_callable($entry[$feature] ?? null)){
			trigger_error("feature not available: {$feature}", E_USER_WARNING);
			continue;
		}
		$enabled[$feature] = true;
		ext('enable', $feature);
	}
	return null;
}
