<?php
declare(strict_types=1);
namespace ff\filter;

use function ff\container;

/**
 * 解析规则集，将 input/filter 的 $set 格式解析为标准化的 [rule, set] 有序数组。
 * 供 input() 和 filter() 共同使用，识别 type/check/abbr 并展开逗号简写与命名 key。
 * ```
 * rules(['int', 'body']);                                    // [['type','int'], ['from','body']]
 * rules(['str', 'email']);                                   // [['type','str'], ['check','email',null]]
 * rules(['str', 'cmp' => ['op' => '>=', 'value' => 18]]); // [['type','str'], ['check','cmp',[...]]]
 * rules(['name' => ['str']]);                                // ['name' => ['str']]
 * rules([fn($v) => $v > 0]);                                // [['check', closure, null]]
 * ```
 * @param array $set 规则集（字符串数组、逗号简写、命名 key、闭包）
 * @return array 有序数组，每项为 [name, set] 或 [name, set, param]
 * @internal
 */
function rules(array $set): array{
	$r = [];
	$typeRules = container('#input.type');
	$checkRules = container('#input.check');
	$abbrRules = container('#input.abbr');
	$parseRules = null;
	foreach($set as $key => $item){
		if(is_int($key) && !is_string($item) && is_callable($item)) $r[] = ['check', $item, null];
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
						$r = [...$r, ...rules($abbr)];
						continue;
					}
				}
				if(isset($typeRules[$k])) $r[] = ['type', $k];
				elseif(isset($checkRules[$k])) $r[] = ['check', $k, $v];
				elseif($v !== null) $r[] = [$k, $v];
				else{
					$parseRules ??= container('#input.parse') ?? [];
					$matched = false;
					foreach($parseRules as $pattern => $parser){
						if(preg_match($pattern, $k, $m)){
							$r = [...$r, ...rules($parser($m))];
							$matched = true;
							break;
						}
					}
					if(!$matched) throw new \InvalidArgumentException("Unknown rule: $k");
				}
			}
		}
		elseif(is_string($key)){
			if(isset($checkRules[$key])) $r[] = ['check', $key, $item];
			elseif(isset($typeRules[$key])) $r[] = ['type', $key];
			else $r[] = [$key, $item];
		}
	}
	return $r;
}