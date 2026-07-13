<?php
declare(strict_types=1);
namespace ff;

use function ff\filter\{check, rules};

/**
 * 验证并转换数据。组合 type + empty + check 规则，失败返回 null。
 * 参数结构与 input 单字段 $set 格式一致，不支持 from/key/null/error/defaults。
 * 内部通过 filter\rules() 解析规则、filter\check() 执行类型转换与验证。
 * ```
 * filter('123', 'int');                                     // 123
 * filter('abc', 'int');                                     // null（type 转换失败）
 * filter('test@example.com', 'email');                      // 'test@example.com'
 * filter('bad', 'email');                                   // null
 * filter(20, ['int', 'digit' => ['op' => '>=', 'value' => 18]]); // 20
 * filter(15, ['int', 'digit' => ['op' => '>=', 'value' => 18]]); // null
 * filter('abc', fn($v) => strlen($v) > 2);                 // 'abc'
 * filter('ab', fn($v) => strlen($v) > 2);                  // null
 * filter('', ['str', 'empty' => 'default', 'default' => 'N/A']); // 'N/A'
 * ```
 * @param mixed                 $var 待验证的值
 * @param string|array|callable $set type/check/empty 规则，与 input 单字段规则格式一致
 * @return mixed 通过返回值，失败返回 null
 */
function filter(mixed $var, array|string|callable $set = []): mixed{
	if(empty($set)) return $var;
	$config = ['check' => [], 'error' => []];
	foreach(rules(is_array($set) ? $set : [$set]) as $rule){
		[$name, $_set] = $rule;
		match ($name) {
			'type', 'default' => $config[$name] = $_set,
			'empty' => $config[$name] = match (true) {
				is_array($_set) => $_set,
				in_array($_set, ['remove', 'throw', 'default']) => ['fail' => $_set],
				default => ['fail' => 'default', 'default' => $_set],
			},
			'error' => $config['error'] = match (true) {
				is_array($_set) => $_set,
				is_int($_set) => ['code' => $_set],
				is_string($_set) => ['message' => $_set],
				default => [],
			},
			'check' => $config['check'][] = [$_set, $rule[2] ?? null],
			default => throw new \InvalidArgumentException("filter 不支持规则 '$name'"),
		};
	}
	$gen = check($var, $config);
	foreach($gen as [$name, $r]){
		if('empty' === $name){
			if($r['fail'] === 'remove') return null;
			if($r['fail'] === 'throw') return null;
		}
		elseif(is_bool($r)){
			if(!$r) return null;
		}
		elseif(is_array($r) && count($r) === 2){
			[$action, $opts] = $r;
			if(is_bool($action)) $action = $action ? 'pass' : 'throw';
			if(in_array($action, ['throw', 'default', 'remove'])) return null;
		}
	}
	return $gen->getReturn();
}
