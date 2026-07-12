<?php
declare(strict_types=1);
namespace ff;
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
	static $parseSet = function(array $set, &$parseSet): array{
		$r = [];
		$typeRules = container('#input.type');
		$checkRules = container('#input.check');
		$abbrRules = container('#input.abbr');
		foreach($set as $key => $item){
			if(is_int($key) && is_callable($item)) $r[] = ['check', $item, null];
			elseif(is_int($key) && is_string($item)){
				foreach(explode(',', $item) as $part){
					if(str_contains($part, ':')){
						[$k, $v] = explode(':', $part, 2);
						$k = trim($k);
						$v = trim($v);
					}
					else [$k, $v] = [trim($part), null];
					if(isset($abbrRules[$k])){
						$abbr = $abbrRules[$k];
						if(is_string($abbr)) $k = $abbr;
						else{
							$r = [...$r, ...$parseSet($abbr, $parseSet)];
							continue;
						}
					}
					if(isset($typeRules[$k])) $r[] = ['type', $k];
					elseif(isset($checkRules[$k])) $r[] = ['check', $k, $v];
					elseif($v !== null) $r[] = [$k, $v];
					else throw new \InvalidArgumentException("Unknown rule: $k");
				}
			}
			elseif(is_string($key)){
				if(isset($checkRules[$key])) $r[] = ['check', $key, $item];
				elseif(isset($typeRules[$key])) $r[] = ['type', $key];
				else $r[] = [$key, $item];
			}
		}
		return $r;
	};
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
	$defaults = $defaults ? $parseSet($defaults, $parseSet) : [];
	foreach($rules as $field => $set){
		$setArr = is_array($set) ? $set : [$set];
		$config = ['error' => []];
		$checks = [];
		foreach([...$defaults, ...$parseSet($setArr, $parseSet)] as $rule){
			[$name, $_set] = $rule;
			match ($name) {
				'type', 'from', 'key', 'default' => $config[$name] = $_set,
				'null', 'empty' => $config[$name] = $parseNull($_set),
				'error' => $config['error'] = $parseError($_set),
				'check' => $checks[] = [$_set, $rule[2] ?? null],
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
		if($type = $config['type'] ?? null) $value = safe(container('#input.type.' . $type), $value);
		if(empty($value) && ($_set = $config['empty'] ?? null)){
			if($_set['fail'] === 'remove') continue;
			elseif($_set['fail'] === 'throw') $throw($parseError($_set['error'] ?? null) + $config['error'], "$field is empty");
			elseif($_set['fail'] === 'default') $value = $_set['default'] ?? $config['default'] ?? null;
		}
		foreach($checks as [$name, $param]){
			$r = (is_string($name) ? container('#input.check.' . $name) : $name)(...(null === $param ? [$value] : [$value, $param]));
			if(is_bool($r)){
				if(!$r) $throw($config['error'], "Validation failed: $field");
			}
			elseif(is_array($r) && count($r) === 2){
				[$action, $opts] = $r;
				if(is_bool($action)) $action = $action ? 'pass' : ($opts['fail'] ?? 'throw');
				if($action === 'throw') $throw($parseError(is_array($opts) ? $opts : null) + $config['error'], "Validation failed: $field");
				elseif($action === 'default') $value = is_array($opts) ? ($opts['default'] ?? $opts['value'] ?? $config['default']) : $config['default'];
				elseif($action === 'remove') continue 2;
			}
		}
		$result[$field] = $value;
	}
	return $result;
}
