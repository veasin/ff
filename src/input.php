<?php
declare(strict_types=1);
namespace ff;

use function ff\filter\check;
use function ff\filter\rules;

/**
 * 批量获取输入数据并验证。组合 from() + null/empty 处理 + 类型转换 + 检查验证
 * ```
 * input(['name' => ['str'], 'age' => ['int', 'null' => 0]]);
 * input(['name' => 'str,body', 'age' => 'int,>=:18']);
 * input(['id' => ['int', 'query']], ['from' => 'body', 'null' => 0]);
 * input(['x' => ['str', 'key' => 'name']]);   // key 规则：lookup key ≠ 字段名
 * ```
 * @param array $rules    字段规则映射，key 为字段名，value 为规则数组/字符串/闭包
 * @param array $defaults 默认规则集，所有字段继承并可被覆盖（仅 from/key/null/empty/default/error/type + check）
 * @return array 关联数组，key 为字段名，value 为处理后的值；null 处理为 remove 的字段不在结果中
 */
function input(array $rules, array $defaults = []): array{
	$result = [];
	static $parseNull = function($set){
		if(is_array($set)) return $set;
		if(in_array($set, ['remove', 'throw', 'default'])) return ['fail' => $set];
		return ['fail' => 'default', 'default' => $set];
	};
	static $parseError = function($set){
		if(is_array($set)) return $set;
		if(is_int($set)) return ['code' => $set];
		if(is_string($set)) return ['message' => $set];
		return [];
	};
	static $throw = function($error, $msg){
		$class = $error['exception'] ?? \RuntimeException::class;
		throw new $class($error['message'] ?? $msg, $error['code'] ?? 0);
	};
	$defaults = $defaults ? rules($defaults) : [];
	foreach($rules as $field => $set){
		$setArr = is_array($set) ? $set : [$set];
		$config = ['check' => [], 'error' => []];
		foreach([...$defaults, ...rules($setArr)] as $rule){
			[$name, $_set] = $rule;
			match ($name) {
				'type', 'from', 'key', 'default' => $config[$name] = $_set,
				'null', 'empty' => $config[$name] = $parseNull($_set),
				'error' => $config['error'] = $parseError($_set),
				'check' => $config['check'][] = [$_set, $rule[2] ?? null],
			};
		}
		$key = $config['key'] ?? $field;
		$from = $config['from'] ?? 'body';
		if($from instanceof \ArrayAccess) $value = $from[$key] ?? null;
		elseif(is_callable($from)) $value = $from($key);
		else $value = from($key, $from);
		if(null === $value && ($_set = $config['null'] ?? null)){
			if($_set['fail'] === 'remove') continue;
			elseif($_set['fail'] === 'throw') $throw($parseError($_set['error'] ?? null) + $config['error'], "$field is required");
			elseif($_set['fail'] === 'default') $value = $_set['default'] ?? $config['default'] ?? null;
		}
		$gen = check($value, $config);
		foreach($gen as [$rule, $_set]){
			if('empty' === $rule){
				if($_set['fail'] === 'remove') continue 2;
				elseif($_set['fail'] === 'throw') $throw($parseError($_set['error'] ?? null) + $config['error'], "$field is empty");
				//elseif($_set['fail'] === 'default') $value = $_set['default'] ?? $config['default'] ?? null;
			}
			else{
				if(is_bool($_set)) $throw($parseError($config['error']), "Validation failed: $field");
				[$action, $opts] = $_set;
				if(is_bool($action)) $action = $action ? 'pass' : ($opts['fail'] ?? 'throw');
				if($action === 'throw') $throw($parseError(is_array($opts) ? $opts : null) + $config['error'], "Validation failed: $field");
				//elseif($action === 'default') $value = is_array($opts) ? ($opts['default'] ?? $opts['value'] ?? $config['default']) : $config['default'];
				elseif($action === 'remove') continue 2;
			}
		}
		$result[$field] = $gen->getReturn();
	}
	return $result;
}
