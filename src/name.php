<?php
declare(strict_types=1);
namespace nx;
/**
 * 命名配置和管理，统一项目中所有类型的命名规则。通过 container('name') 配置命名模板。
 * ```
 * container('name', ['cache' => ['user' => 'cache:user:{uid}']]);
 * $key = name('user.id');                                        // 返回 'user.id'（无模板）
 * $key = name('user', ['uid' => 123], 'cache');                  // 返回 'cache:user:123'
 * ```
 * @param string      $keyConfigNameOrKeyTemplate 配置中的 key 或 key 模板
 * @param array|null  $context                    上下文数据，替换模板中 {placeholder}
 * @param string|null $namespace                  命名空间，区分不同类型的配置
 * @return string 处理后的 key
 */
function name(string $keyConfigNameOrKeyTemplate, ?array $context = null, ?string $namespace = null): string{
	$config = container('#name') ?? [];
	$key = $namespace
		? ($config[$namespace][$keyConfigNameOrKeyTemplate] ?? $keyConfigNameOrKeyTemplate)
		: $keyConfigNameOrKeyTemplate;
	return null === $context ? $key : preg_replace_callback('/\{([^}]+)\}/', fn($m) => $context[$m[1]] ?? $m[0], $key);
}
