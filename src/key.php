<?php
declare(strict_types=1);
namespace ff;

/**
 * 低层级容器结构化读取
 *
 * 从容器路径读取值，按 layer 选取后原样返回。
 * null 返回 null；array 按 layer 选取（null 取 0 号默认）；其余值原样返回。
 * 广泛适用于翻译、键命名、环境配置等按层切换的场景。
 *
 * ```
 * // 翻译（框架内部使用）
 * $msg = key('#ff.error.internal');                    // '服务器内部错误'
 * $msg = key('#ff.error.internal', 'en_US');            // 'Internal server error'
 *
 * // 缓存键（按驱动选层）
 * container('^#key', [
 *     'user' => ['user:{id}', 'redis' => 'u:{id}', 'apcu' => 'uc:{id}'],
 * ]);
 * $cacheKey = key('#key.user');                         // 'user:{id}'（默认）
 * $cacheKey = key('#key.user', 'redis');                // 'u:{id}'（redis 层）
 * $cacheKey = key('#key.user', 'apcu');                 // 'uc:{id}'（apcu 层）
 *
 * // 环境配置（按部署环境选层）
 * container('^#cfg', [
 *     'api_url' => ['https://api.example.com', 'staging' => 'https://staging.api.example.com'],
 *     'debug'   => ['false', 'dev' => 'true'],
 * ]);
 * $apiUrl = key('#cfg.api_url', 'staging');             // 'https://staging.api.example.com'
 * $debug  = key('#cfg.debug', 'dev');                   // 'true'
 *
 * // 未命中
 * $val = key('#cfg.not_exists');                        // null
 * ```
 *
 * @param  string      $path  容器路径
 * @param  string|null $layer 选取层，null 取 0 号默认
 * @return mixed 有值原样返回，null 未命中
 */
function key(string $path, ?string $layer = null): mixed{
	$entry = container($path);
	if($entry === null) return null;
	if(is_array($entry)) return $entry[$layer] ?? $entry[0] ?? null;
	return $entry;
}
