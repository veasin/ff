<?php
declare(strict_types=1);
namespace nx;
/**
 * 日志函数，PSR-3 风格输出，内部通过容器注入 PSR Logger
 * ```
 * // 注入 PSR Logger
 * container('#log', $logger);
 * // 注入闭包
 * container('#log', ['fn' => fn($level, $message, $context) => ...]);
 * // 使用
 * log('用户 {user} 登录', ['user' => 'admin'], 'info');
 * log('错误: 连接失败', 'error');
 * log(['a' => 1, 'b' => 2]);  // 非string自动json
 * log('调试信息', 'debug');
 * ```
 * @param string|array|object $message 日志消息，支持占位符，非string自动json
 * @param array|string|null   $context 替换占位符的关联数组，或直接传level
 * @param string              $level   日志级别: emergency|alert|critical|error|warning|notice|info|debug
 * @return void
 */
function log(string|array|object $message, array|string|null $context = null, string $level = 'info'): void{
	static $levels = [
		'emergency' => 0,
		'alert' => 1,
		'critical' => 2,
		'error' => 3,
		'warning' => 4,
		'notice' => 5,
		'info' => 6,
		'debug' => 7,
	];
	if(is_string($context)) [$level, $context] = [$context, []];
	else $context ??= [];
	$level = strtolower($level);
	if(!isset($levels[$level])) $level = 'info';
	if(container('#log')){
		$logger = container('#log');
		$fn = null;
		if(is_callable($logger)) $fn = $logger;
		if($logger && is_object($logger) && method_exists($logger, 'log')) $fn = $logger->log(...);
		if($fn){
			$fn($level, $message, $context);
			return;
		}
	}
	$msg = $message instanceof \Stringable ? (string)$message : (is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE));
	$msg = empty($context)
		? $msg
		: strtr($msg,
			array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($context)),
				array_map(fn($v) => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE), array_values($context))));
	error_log("[$level] $msg");
}