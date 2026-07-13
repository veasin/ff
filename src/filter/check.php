<?php
declare(strict_types=1);
namespace ff\filter;

use function ff\container;
use function ff\safe;

/**
 * 执行 type 转换 + empty 检查 + check 验证，通过生成器逐步 yield 结果，最终 return 处理后的值。
 * 供 input() 和 filter() 共同使用，调用方通过 foreach 消费 yield 决定后续行为（throw/return null 等）。
 * ```
 * $gen = check('test@example.com', ['check' => [['email', null]]]);
 * $gen->current(); // ['email', true]（bool 通过时不 yield，直接进入下一个 check）
 * $gen->getReturn(); // 'test@example.com'
 * $gen = check('', ['empty' => ['fail' => 'throw'], 'check' => []]);
 * $gen->current(); // ['empty', ['fail' => 'throw']]
 * ```
 * @param mixed $value 待验证的值
 * @param array $rules 已解析的配置，含 type/empty/default/check/error
 * @return \Generator 生成器，yield [$name, $result]；$name 为 'empty' 或 check 名
 *                     $result 为 false（bool 失败）、[action, $opts]（结构化失败）、或不 yield（bool 通过）
 *                     getReturn() 返回处理后的值（type 转换后、empty default 替换后）
 * @internal
 */
function check(mixed $value, array $rules): \Generator{
	if($type = $rules['type'] ?? null) $value = safe(container('#input.type.' . $type), $value);
	if(empty($value) && ($_set = $rules['empty'] ?? null)){
		yield ['empty', $_set];
		if($_set['fail'] === 'default') $value = $_set['default'] ?? $rules['default'] ?? null;
	}
	foreach($rules['check'] as [$name, $param]){
		$r = (is_string($name) ? container('#input.check.' . $name) : $name)(...(null === $param ? [$value] : [$value, $param]));
		if(is_bool($r)){
			if(!$r) yield [$name, false];
		}
		elseif(is_array($r) && count($r) === 2){
			yield [$name, $r];
			if($r[0] === 'default') $value = is_array($r[1]) ? ($r[1]['default'] ?? $r[1]['value'] ?? $rules['default']) : $rules['default'];
		}
	}
	return $value;
}