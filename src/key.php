<?php
declare(strict_types=1);
namespace ff;
/**
 * 命名键管理，统一项目中所有缓存/存储 key 的命名规则。通过 container('#key') 配置命名模板。
 * 配置按业务实体聚合，0 号元素为默认模板，命名键为特定层覆盖：
 * ```
 * container('#key', [
 *     'user'    => ['user:{id}', 'apcu' => 'user_cache:{id}', 'redis' => 'user:{id}'],
 *     'session' => ['sess:{token}'],
 *     'article' => 'art:{id}',
 * ]);
 * $key = key('user', ['id' => 123]);                    // 返回 'user:123'
 * $key = key('user', ['id' => 123], 'apcu');            // 返回 'user_cache:123'
 * $key = key('session', ['token' => 'abc'], 'redis');   // 返回 'sess:abc'（回退 0 号）
 * $key = key('article');                                // 返回 'art:{id}'
 * ```
 * @param string      $name    配置中的键名，或直接作为模板
 * @param array|null  $context 上下文数据，替换模板中 {placeholder}
 * @param string|null $layer   缓存层名称，null 表示使用默认模板
 * @return string 处理后的 key
 */
function key(string $name, ?array $context = null, ?string $layer = null): string{
	$def = (array) ((container('#key') ?? [])[$name] ?? null);
	$tpl = $def
		? ($layer !== null ? ($def[$layer] ?? $def[0] ?? $name) : ($def[0] ?? $name))
		: $name;
	return $context
		? preg_replace_callback('/\{(\w+)\}/', fn($m) => $context[$m[1]] ?? $m[0], $tpl)
		: $tpl;
}
