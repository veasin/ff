<?php
declare(strict_types=1);
namespace nx;
/**
 * 多语言翻译函数，支持占位符替换和强制语言。
 * ```
 * i18n(lang: 'en_US');                               // 设置当前语言
 * $msg = i18n('#error:internal');                     // 框架翻译
 * $msg = i18n('welcome', ['name' => '张三']);          // 用户翻译 + {name} 替换
 * $msg = i18n('#error:internal', 'en_US');             // 强制语言
 * $msg = i18n('welcome', ['name' => 'Alice'], 'en_US');// 强制语言 + 替换
 * $msg = i18n('welcome.name', ['name' => 'A']);        // . 自动转 _
 * ```
 * 框架默认翻译内置在 container core 中，用户可单条增量覆盖：
 * ```
 * container('i18n.zh_CN.#error:internal', '自定义错误');
 * container('^i18n.lang', 'en_US');                    // 持久化语言设置
 * ```
 * @param string|null       $key    框架键 #模块:key 或用户自定义 key，. 自动转 _
 * @param array|string|null $params 占位符数组，或 string 时作为强制语言
 * @param string|null       $lang   强制语言代码（优先级最高）
 * @return string|null 翻译结果或 null（设置语言时）
 */
function i18n(?string $key = null, null|string|array $params = null, ?string $lang = null): ?string{
	$key = $key !== null ? str_replace('.', '_', $key) : null;
	if($key === null && $lang !== null) return container('i18n.lang', $lang);
	if(is_string($params)) [$lang, $params] = [$params, null];
	$lang ??= container('i18n.lang');
	if($lang === null || $key === null || $key === '') return $key ?? '';
	$text = container("i18n.{$lang}.{$key}");
	if($text === null) return $key;
	if($params) foreach($params as $k => $v) $text = str_replace("{{$k}}", (string)$v, $text);
	return $text;
}
