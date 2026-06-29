<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{cache, test};
use function ff\cache\apcu;

$mock = [];
if(!function_exists('apcu_fetch')){
	function apcu_fetch($key, &$success = null){
		global $mock;
		if(is_array($key)){
			$result = [];
			$all = true;
			foreach($key as $k){
				if(array_key_exists($k, $mock)){
					$result[$k] = $mock[$k];
				}else{ $all = false; }
			}
			$success = $all;
			return $result;
		}
		$success = array_key_exists($key, $mock);
		return $success ? $mock[$key] : false;
	}
	function apcu_store($key, $value, $ttl = 0){
		global $mock; $mock[$key] = $value; return true;
	}
	function apcu_delete($key){
		global $mock; unset($mock[$key]); return true;
	}
	function apcu_clear_cache(){
		global $mock; $mock = []; return true;
	}
}
$mock = [];

test('未命中时计算并存储',
	cache(apcu('test_key', middleware: ['ttl' => 60]), fn($next) => 'computed'),
	'computed'
);
test('命中时直接返回缓存',
	cache(apcu('test_key', middleware: true), fn($next) => 'should not run'),
	'computed'
);
test('不同键不命中',
	cache(apcu('other_key', middleware: true), fn($next) => 'fresh'),
	'fresh'
);
test('多级链中缓存中间件',
	cache(apcu('chain_key', middleware: ['ttl' => 60]), fn($next) => 'chain_value'),
	'chain_value'
);
test('null 值不被缓存且兜底返回 null',
	cache(apcu('null_test', middleware: true), fn($next) => null),
	null
);
test('factory TTL 简写 int',
	cache(apcu('ttl_key', middleware: 60), fn($next) => 'ttl_value'),
	'ttl_value'
);
test('$value 作为兜底默认值',
	cache(apcu('miss_key', 'fallback_val', middleware: true), fn($next) => $next()),
	'fallback_val'
);

test();
