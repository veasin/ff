<?php
declare(strict_types=1);
namespace ff;
/**
 * 验证并转换数据。按顺序应用规则，任意验证失败返回 null。
 * 内置规则: int|str|email|url|number|json|bool，通过 container('#filter') 扩展自定义规则。
 * ```
 * filter('123', 'int');                          // 类型转换: 返回 123 (int)
 * filter('true', 'bool');                        // 类型转换: 返回 true
 * filter('hello@example.com', 'email');           // 验证: 返回邮箱字符串
 * filter(3, 'int', '>5');                        // 参数化验证: 返回 null (3不大于5)
 * filter('abc', fn($v) => strlen($v) > 2);       // 自定义闭包: 返回 'abc'
 * filter('150', 'int,>0,<200');                  // 组合规则: 返回 150
 * container('#filter.phone', [null, null, [fn($v) => preg_match('/^1\d{10}$/', $v)]]);
 * filter('13800138000', 'phone');                 // 自定义规则: 返回 '13800138000'
 * ```
 * @param mixed $var 待验证的数据
 * @param string|array|callable ...$rules 验证规则：
 *        - 预定义类型: 'int', 'str', 'email', 'url', 'json', 'bool'
 *        - 参数化规则: '>100', '<50', '>=18', '<=99'
 *        - 逗号分隔: 'int,>0,<100'
 *        - 自定义函数: fn($v) => $v > 0
 *        - 数组格式: ['number', ['opt' => '>', 'number' => 100]]
 * @return mixed 验证通过返回转换后的值，失败返回 null
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


