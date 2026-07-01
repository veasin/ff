<?php
declare(strict_types=1);
namespace ff;
/**
 * 多语言翻译函数，支持占位符替换和强制语言。
 * 首次调用时自动注入默认翻译到容器持久层，不增加用户流程。
 * ```
 * i18n(lang: 'en_US');                                     // 设置当前语言
 * $msg = i18n('#error:internal');                     // 框架翻译
 * $msg = i18n('{message}', ['message' => 'error msg']);    // 无翻译时，{message} 替换为 'error msg'
 * $msg = i18n('welcome', ['name' => '张三']);              // 用户翻译 + {name} 替换
 * $msg = i18n('#error:internal', 'en_US');             // 强制语言
 * $msg = i18n('welcome', ['name' => 'Alice'], 'en_US');    // 强制语言 + 替换
 * $msg = i18n('welcome.name', ['name' => 'A']);            // . 自动转 _
 * ```
 * 用户可在首次调用前通过容器单条覆盖，惰性注入不会覆盖已有值：
 * ```
 * container('i18n.zh_CN.#error:internal', '自定义错误');    // 请求级覆盖
 * container('^i18n.lang', 'en_US');                    // 持久化语言设置
 * ```
 * @param string|null       $key    框架键 #模块:key 或用户自定义 key，. 自动转 _
 * @param array|string|null $params 占位符数组，或 string 时作为强制语言
 * @param string|null       $lang   强制语言代码（优先级最高）
 * @return string|null 翻译结果或 null（设置语言时）
 */
function i18n(?string $key = null, null|string|array $params = null, ?string $lang = null): ?string{
	static $loaded = false;
	static $fallback = [
		'zh_CN' => [
			'#error:internal' => '服务器内部错误',
			'#error:rate_limit' => '请求过于频繁，请稍后再试',
			'#error:csrf_mismatch' => 'CSRF 令牌不匹配',
			'#auth:apikey_required' => '需要 API Key',
			'#auth:realm_basic' => '需要认证',
			'#auth:realm_token' => '需要 Token 认证',
			'#auth:realm_jwt' => '需要 JWT 认证',
			'#container:key_empty' => '键名不能为空',
			'#container:star_warn' => 'container 写入操作带 * 后缀无效: {key}',
			'#test:passed' => '[g]✔ 全部通过[y]: {passed}/{total}',
			'#test:failed' => '[r]● 测试失败[y]: {count}, [g]{passed}[y]/{total}',
			'#test:case' => "[r]▶ {label}\n\t[n]预期: {expected}\n\t[n]实际: {actual}",
		],
		'en_US' => [
			'#error:internal' => 'Internal server error',
			'#error:rate_limit' => 'Too many requests. Please try again later.',
			'#error:csrf_mismatch' => 'CSRF token mismatch',
			'#auth:apikey_required' => 'API Key required',
			'#auth:realm_basic' => 'Authentication required',
			'#auth:realm_token' => 'Token authentication required',
			'#auth:realm_jwt' => 'JWT authentication required',
			'#container:key_empty' => 'Key cannot be empty',
			'#container:star_warn' => 'container write with * suffix has no effect: {key}',
			'#test:passed' => '[g]✔ All passed[y]: {passed}/{total}',
			'#test:failed' => '[r]● Test failed[y]: {count}, [g]{passed}[y]/{total}',
			'#test:case' => "[r]▶ {label}\n\t[n]Expected: {expected}\n\t[n]Actual: {actual}",
		],
	];
	if(!$loaded){
		$loaded = true;
		if(container('^i18n.lang') === null) container('^i18n.lang', 'zh_CN');
		foreach($fallback as $langKey => $translations){
			foreach($translations as $k => $v){
				if(container("^i18n.$langKey.$k") === null) container("^i18n.$langKey.$k", $v);
			}
		}
	}
	$key = $key !== null ? str_replace('.', '_', $key) : null;
	if($key === null && $lang !== null) return container('i18n.lang', $lang);
	if(is_string($params)) [$lang, $params] = [$params, null];
	$lang ??= container('i18n.lang');
	if($lang === null || $key === null || $key === '') return $key ?? '';
	$text = container("i18n.$lang.$key") ?? $key;
	if($params) foreach($params as $k => $v) $text = str_replace("{{$k}}", (string)$v, $text);
	return $text;
}
