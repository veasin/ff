<?php
namespace nx;
/**
 * 命名配置和管理，统一项目中所有类型的使用key
 * @param string      $keyConfigNameOrKeyTemplate 配置中的key或key的模板
 * @param array|null  $context                    上下文数据，用于替换模板中的占位符
 * @param string|null $namespace                  命名空间，用于区分不同类型的配置
 * @return string 处理后的key
 */
function name(string $keyConfigNameOrKeyTemplate, ?array $context = null, ?string $namespace = null): string{
	$config = container('#name') ?? [];
	$key = $namespace
		? ($config[$namespace][$keyConfigNameOrKeyTemplate] ?? $keyConfigNameOrKeyTemplate)
		: $keyConfigNameOrKeyTemplate;
	return null === $context ? $key : preg_replace_callback('/\{([^}]+)\}/', fn($m) => $context[$m[1]] ?? $m[0], $key);
}
