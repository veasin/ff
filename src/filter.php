<?php
namespace nx;
/**
 * 验证并转换数据
 * 对输入数据应用一系列规则进行验证和转换。规则可以是预定义类型、自定义验证函数或参数化规则。
 * 所有规则按顺序应用，如果任何验证失败则返回null。
 * ```
 * filter('123', 'int'); // 返回 123
 * filter('hello@example.com', 'email'); // 返回邮箱字符串
 * filter('150', 'int', '>100', '<200'); // 返回 150
 * filter('on', 'bool'); // 返回 true
 * filter('abc', fn($v) => strlen($v) > 2); // 返回 'abc'
 * ```
 * @param mixed $var 待验证的数据
 * @param string|array|callable ...$rules 验证规则，支持多种格式：
 *        - 预定义类型字符串: 'int', 'str', 'email', 'url', 'json', 'bool'
 *        - 带参数的规则: '>100', '<50', '>=18'
 *        - 逗号分隔的组合规则: 'int,>0,<100'
 *        - 自定义验证函数: fn($v) => $v > 0
 *        - 数组格式: ['number', ['opt' => '>', 'number' => 100]]
 * @return mixed|null 验证通过则返回转换后的值，否则返回null
 */
function filter(mixed $var, string|array|callable ...$rules): mixed{
	if(empty($rules)) return $var;
	static $defaultRules = [
		'int' => [null, fn($v) => (int)$v, null],
		'str' => [null, fn($v) => (string)$v, null],
		'email' => [null, null, [fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL)]],
		'url' => [null, null, [fn($v) => filter_var($v, FILTER_VALIDATE_URL)]],
		'number' => [
			fn($rule) => preg_match('/^([><=]+)(\d+)$/', $rule, $matches) ? ['opt' => $matches[1], 'number' => (int)$matches[2]] : false, null, [
				fn($v, $params) => match ($params['opt']??null) {
					'>' => $v > $params['number'],
					'<' => $v < $params['number'],
					'>=' => $v >= $params['number'],
					'<=' => $v <= $params['number'],
					default => true
				},
			],
		],
		'json' => [null, fn($v) => json_decode($v, true), null],
		'bool' => [null, fn($v) => match (strtolower((string)$v)) {
			'1', 'true', 'yes', 'on' => true,
			'0', 'false', 'no', 'off' => false,
			default => null
		}, null],
	];
	$rulesConfig = [...$defaultRules, ...(container('#filter') ?? [])];
	$converter = null;
	$validators = [];
	foreach($rules as $dirty){
		if(is_callable($dirty)){
			$validators[] = [$dirty];
			continue;
		}
		if(is_string($dirty)){
			foreach(explode(',', $dirty) as $part){
				$part = trim($part);
				$parsed = false;
				foreach($rulesConfig as [$parse, $convert, $vs]){
					if($parse){
						$params = $parse($part);
						if($params !== false){
							$parsed = true;
							if($convert) $converter = $convert;
							if($vs) $validators[] = [$vs, $params];
							break;
						}
					} elseif(isset($rulesConfig[$part])){// 处理没有解析器的规则，如 'int', 'str'
						[, $convert, $vs] = $rulesConfig[$part];
						if($convert) $converter = $convert;
						if($vs) $validators[] = [$vs, null];
						$parsed = true;
						break;
					}
				}
				if(!$parsed) return null;
			}
		}
		elseif(is_array($dirty) && isset($rulesConfig[$dirty[0] ?? ''])){
			[, $convert, $vs] = $rulesConfig[$dirty[0]];
			if(isset($convert)) $converter = $convert;
			if(isset($vs)) $validators[] = [$vs, $dirty[1] ?? null];
		}
	}
	if($converter) $var = $converter($var);
	foreach($validators as [$fns, $params]){
		foreach($fns as $fn) if(!$fn($var, $params)) return null;
	}
	return $var;
}


