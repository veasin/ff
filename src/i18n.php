<?php
declare(strict_types=1);
namespace ff;
/**
 * 多语言翻译
 * 支持占位符替换和强制语言。框架内置翻译键格式 `#ff.{模块}.{key}`。
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
	static $loaded = false;
	static $fallback = [
		'error' => [
			'internal' => ['服务器内部错误', 'en_US' => 'Internal server error'],
			'rate_limit' => ['请求过于频繁，请稍后再试', 'en_US' => 'Too many requests. Please try again later.'],
			'csrf_mismatch' => ['CSRF 令牌不匹配', 'en_US' => 'CSRF token mismatch'],
		],
		'auth' => [
			'apikey_required' => ['需要 API Key', 'en_US' => 'API Key required'],
			'realm_basic' => ['需要认证', 'en_US' => 'Authentication required'],
			'realm_token' => ['需要 Token 认证', 'en_US' => 'Token authentication required'],
			'realm_jwt' => ['需要 JWT 认证', 'en_US' => 'JWT authentication required'],
		],
		'container' => [
			'key_empty' => ['键名不能为空', 'en_US' => 'Key cannot be empty'],
			'star_warn' => ['container 写入操作带 * 后缀无效: {key}', 'en_US' => 'container write with * suffix has no effect: {key}'],
		],
		'test' => [
			'passed' => ['[g]✔ 全部通过[y]: {passed}/{total}', 'en_US' => '[g]✔ All passed[y]: {passed}/{total}'],
			'failed' => ['[r]● 测试失败[y]: {count}, [g]{passed}[y]/{total}', 'en_US' => '[r]● Test failed[y]: {count}, [g]{passed}[y]/{total}'],
			'case' => ['[r]▶ {label}\n\t[n]预期: {expected}\n\t[n]实际: {actual}', 'en_US' => "[r]▶ {label}\n\t[n]Expected: {expected}\n\t[n]Actual: {actual}"],
		],
	];
	if(!$loaded){
		$loaded = true;
		if(container('^i18n.lang') === null) container('^i18n.lang', 'zh_CN');
		container('^i18n.#ff', $fallback);
	}
	if($key === null && $lang !== null) return container('i18n.lang', $lang);
	if(is_string($params)) [$lang, $params] = [$params, null];
	if($key === null || $key === '') return $key ?? '';
	return name($key, $params, $lang ?? container('i18n.lang'), 'i18n.');
}
