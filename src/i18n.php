<?php
declare(strict_types=1);
namespace ff;
/**
 * 多语言翻译
 * 支持占位符替换和强制语言。框架内置翻译键格式 `#ff.{模块}.{key}`，默认翻译由各子项目注入。
 * 内部调用 `name()` 实现，用户翻译通过容器按命名空间写入。
 * ```
 * i18n(lang: 'en_US');                                          // 设置当前语言
 * $msg = i18n('#ff.error.internal');                            // 框架翻译
 * $msg = i18n('#ff.error.internal', 'en_US');                   // 强制语言
 * $msg = i18n('myapp.welcome', ['name' => '张三']);              // 占位符替换
 * $msg = i18n('myapp.welcome', ['name' => 'Alice'], 'en_US');    // 强制语言 + 占位符
 * ```
 * 容器配置：
 * - `^i18n.{ns}`: `array` - 用户翻译值，{key} => string|array
 * - `^i18n.lang`: `string` - 持久化当前语言
 * @param string|null       $key    翻译键
 * @param string|array|null $params 替换上下文或语言简写
 * @param string|null       $lang   强制语言，null 使用当前语言
 * @return string|null 翻译值或 null（仅设置语言时返回 null）
 */
function i18n(?string $key = null, null|string|array $params = null, ?string $lang = null): ?string{
	if($key === null && $lang !== null) return container('i18n.lang', $lang);
	if(is_string($params)) [$lang, $params] = [$params, null];
	if($key === null || $key === '') return $key ?? '';
	return name($key, $params, $lang ?? container('i18n.lang'), 'i18n.');
}
