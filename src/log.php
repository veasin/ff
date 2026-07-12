<?php
declare(strict_types=1);
namespace ff;
/**
 * 日志函数，广播到所有 ext 注册的 handler。默认无任何驱动，需自行注册。
 * 支持 ext 扩展：domain='log'，handler 签名 fn(string $level, string|array|object $message, array $context): void，具体参数见 doc/functions.md。
 *
 * log 驱动 handler 接口形式（参照 PSR-3 LoggerInterface::log）：
 * ```
 * function(string $level, string|array|object $message, array $context): void
 * ```
 * $message 支持 `{placeholder}` 占位符，非 string 自动 json_encode。
 *
 * 示例注册：
 * ```
 * container('^#ext.log.error', \ff\log\error(...));
 * log('用户登录');                                    // 默认 level info
 * log('发生错误', 'error');                           // 指定 level
 * log('用户 {name} 登录', ['name' => 'admin']);        // {key} 占位符替换
 * log('错误: {msg}', ['msg' => '失败'], 'error');      // context + level
 * log(['a' => 1, 'b' => 2]);                          // 非 string 自动 json
 * ```
 * @param string|array|object $message 日志消息，支持 {placeholder}；非 string 自动 json_encode
 * @param array|string|null   $context 占位符替换；string 时作为 level（跳位）
 * @param string              $level   日志级别，默认 info，不做校验直接广播
 * @return void
 */
function log(string|array|object $message, array|string|null $context = null, string $level = 'info'): void{
	if(is_string($context)) [$level, $context] = [$context, []];
	else $context ??= [];
	ext('log', false, $level, $message, $context);
}
