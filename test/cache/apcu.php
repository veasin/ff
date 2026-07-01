<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\cache\apcu;

$mock = [];
if(!function_exists('apcu_fetch') || !function_exists('apcu_store')){
	function apcu_fetch($key, &$success = null){
		global $mock;
		if(is_array($key)){
			$result = [];
			$all = true;
			foreach($key as $k){
				if(array_key_exists($k, $mock)){
					$result[$k] = $mock[$k];
				}else{
					$all = false;
				}
			}
			$success = $all;
			return $result;
		}
		$success = array_key_exists($key, $mock);
		return $success ? $mock[$key] : false;
	}
	function apcu_store($key, $value, $ttl = 0){
		global $mock;
		$mock[$key] = $value;
		return true;
	}
	function apcu_delete($key){
		global $mock;
		unset($mock[$key]);
		return true;
	}
	function apcu_clear_cache(){
		global $mock;
		$mock = [];
		return true;
	}
}
$mock = [];

test('无参返回 null', apcu(), null);
test('读取不存在的键', apcu('nonexistent'), null);
test('写入键值', apcu('k1', 'v1'), true);
test('读取已存在的值', apcu('k1'), 'v1');
test('写入带 TTL', apcu('k2', 'v2', 3600), true);
test('TTL 写入后读取', apcu('k2'), 'v2');
test('删除键', apcu('k1', null), true);
test('删除后读取返回 null', apcu('k1'), null);
test('批量读取', apcu(['k1', 'k2', 'k3']), ['k1' => null, 'k2' => 'v2', 'k3' => null]);
test('批量写入', apcu(['k3' => 'v3', 'k4' => 'v4']), null);
test('批量写入后读取验证', apcu(['k3', 'k4']), ['k3' => 'v3', 'k4' => 'v4']);
test('清空全部', apcu(null), true);
test('清空后读取为空', apcu('k2'), null);

container('#apcu.test_cfg', ['prefix' => 'app_', 'ttl' => 300]);
test('config 简写写入带前缀',
	apcu('cfg_key', 'cfg_val', 'test_cfg'),
	true
);
test('config 简写读取带前缀',
	apcu('app_cfg_key'),
	'cfg_val'
);
test('config 数组写入',
	apcu('arr_key', 'arr_val', ['config' => 'test_cfg', 'ttl' => 600]),
	true
);
test('config 数组覆盖 TTL 不影响前缀',
	apcu('app_arr_key'),
	'arr_val'
);

test();
