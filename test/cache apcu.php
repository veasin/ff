<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{cache, container, test};

test('验证APCu扩展', function_exists('apcu_fetch') && function_exists('apcu_cache_info'), true);
test('启用 apc.enable_cli', ini_get('apc.enable_cli'), '1');
// 模拟APCu存储（仅当APCu未启用时）
if(!function_exists('apcu_fetch')){
	$GLOBALS['mock_apcu'] = [];
	function apcu_fetch($key, &$success = null){
		$success = isset($GLOBALS['mock_apcu'][$key]);
		return $success ? $GLOBALS['mock_apcu'][$key] : null;
	}
	function apcu_store($key, $value, $ttl = 0){
		$GLOBALS['mock_apcu'][$key] = $value;
		return true;
	}
	function apcu_exists($key){
		return isset($GLOBALS['mock_apcu'][$key]);
	}
	function apcu_delete($key){
		unset($GLOBALS['mock_apcu'][$key]);
		return true;
	}
}
// 清理模拟存储（如果使用模拟）
if(isset($GLOBALS['mock_apcu'])){
	$GLOBALS['mock_apcu'] = [];
}
// 设置测试配置（包含APCu相关配置，Redis部分仅用于验证配置名传递，不影响APCu测试）
container('cache', [
	'用户资料' => ['APCu' => [3600, '_' => 'user_'], 'Redis' => [7200, '_' => 'user:']],
	'商品详情' => ['APCu' => [1800, '_' => 'prod_']],
	'网站配置' => ['APCu' => [86400, '_' => 'config_'], 'Redis' => [43200, '_' => 'config:']],
]);
// 测试5: 数组格式配置（纯APCu）
test('缓存使用数组配置', cache(['fn' => 'APCu', 'ttl' => 1800, 'key' => '测试键'], fn($next) => '数组配置值'), '数组配置值');
// ========== APCu 专用测试 ==========
test('APCu - 基本读写', cache(['fn' => 'APCu', 'key' => 'apcu_test_key', 'ttl' => 60], fn($next) => 'apcu测试数据'), 'apcu测试数据');
test('APCu - 验证数据已写入', cache(['fn' => 'APCu', 'key' => 'apcu_test_key'], fn($next) => '不应该执行，应该从缓存读取'), 'apcu测试数据');
test('APCu - 使用配置名前缀', cache('用户资料', ['fn' => 'APCu', 'key' => 'profile_123'], fn($next) => '用户资料数据'), '用户资料数据');
// 验证带前缀的key（兼容真实APCu环境）
test('APCu - 验证带前缀的key',
	function(){
		$key = 'user_profile_123';
		if(function_exists('apcu_fetch')){
			return apcu_fetch($key);
		}
		else{
			return isset($GLOBALS['mock_apcu'][$key]) ? $GLOBALS['mock_apcu'][$key] : null;
		}
	},
	'用户资料数据'
);
test('APCu - 缓存未命中时调用下一个', cache(['fn' => 'APCu', 'key' => 'not_exists_key'], fn($next) => '新数据'), '新数据');
test('APCu - TTL配置生效', cache('网站配置', ['fn' => 'APCu', 'key' => 'site_config'], fn($next) => '网站配置数据'), '网站配置数据');
test();