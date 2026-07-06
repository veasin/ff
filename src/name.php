<?php
declare(strict_types=1);
namespace ff;
/**
 * 完整管道读取：读 → 选 → 回退 → 替换
 * 通过 `$prefix` 分离容器读取路径与回退模板。
 * 内部调用 `key(prefix + path, layer)`，未命中时回退 `$path`，
 * 最后对 `{placeholder}` 做上下文替换。
 * ```
 * // prefix 风格——prefix 拼入读取路径，回退时不含 prefix
 * $name = name('user', ['id' => 123], prefix: '#key.');  // 'user:123'
 * $name = name('user', ['id' => 1], 'apcu', '#key.');    // 'user_cache:1'
 * // 无 prefix——path 即完整容器路径
 * $name = name('#key.user', ['id' => 123]);              // 'user:123'
 * ```
 * @param string            $path    回退模板 / 容器路径基底
 * @param string|array|null $context 替换上下文 `{key} => val`，string 简写为 $layer
 * @param string|null       $layer   语言层
 * @param string|null       $prefix  读取前缀，拼入 key() 的路径
 * @return string 命中值、回退 path、或替换后的字符串
 */
function name(string $path, null|string|array $context = null, ?string $layer = null, ?string $prefix = null): string{
	if(is_string($context)) [$layer, $context] = [$context, null];
	$tpl = (string)(key(($prefix ?? '') . $path, $layer) ?? $path);
	return $context ? preg_replace_callback('/{(\w+)}/', fn($m) => $context[$m[1]] ?? $m[0], $tpl) : $tpl;
}
