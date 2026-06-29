<?php
declare(strict_types=1);
namespace ff;
/**
 * 获取并验证输入数据。input() = from() + filter()
 * ```
 * $age = input('age', 'query', 'int', '>=18', '<=100');       // 单值：来源+规则
 * $age = input('age', 'query,int,>=18,<=100');                 // 单值：组合规则字符串
 * $data = input(['id' => 'int,>0', 'name' => 'str']);         // 批量：map 数组+规则
 * $list = input(['id', 'name'], 'body');                       // 批量：list 数组+来源
 * ```
 * 来源名: body|query|header|params|cookie|file，未指定来源时默认 body
 * @param array|string|null $name  键名；null 时返回 null；数组时批量操作
 * @param array|string|null ...$rules 验证规则，含来源名时自动识别；逗号分隔组合规则
 * @return mixed 单值返回验证后的值；批量返回关联数组；失败返回 null
 */
function input(array|string|null $name, array|string|null ...$rules): mixed{
	if(is_array($name)){
		$result = [];
		if(array_is_list($name)){
			foreach($name as $key){
				$result[$key] = input($key, ...($rules ?? []));
			}
		} else{
			foreach($name as $key => $rule){
				$rule = is_array($rule) ? $rule : [$rule];
				$result[$key] = input($key, ...($rules ?? []), ...$rule);
			}
		}
		return $result;
	}
	$source = '';
	$validators = [];
	foreach($rules as $rule){
		if(is_string($rule)){
			foreach(array_map('trim', explode(',', $rule)) as $part){
				if(in_array($part, ['body', 'query', 'header', 'input', 'params', 'cookie', 'file'])) $source = $part;
				else $validators[] = $part;
			}
		}
		elseif(is_array($rule)) $validators = array_merge($validators, $rule);
	}
	$value = from($name, $source ?: 'body');
	return empty($validators) ? $value : filter($value, ...$validators);
}
