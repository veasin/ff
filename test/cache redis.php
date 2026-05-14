<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{cache, container, test};

// 模拟Redis存储
$GLOBALS['mock_redis'] = [];
if(!class_exists('Redis')){
	class Redis{
		private $connected = false;
		public function connect($host, $port){
			$this->connected = true;
			return true;
		}
		public function auth($password){
			return true;
		}
		public function select($database){
			return true;
		}
		public function get($key){
			global $mock_redis;
			return isset($mock_redis[$key]) ? serialize($mock_redis[$key]) : false;
		}
		public function setex($key, $ttl, $value){
			global $mock_redis;
			$mock_redis[$key] = unserialize($value);
			return true;
		}
		public function exists($key){
			global $mock_redis;
			return isset($mock_redis[$key]);
		}
		public function del($key){
			global $mock_redis;
			unset($mock_redis[$key]);
			return true;
		}
	}
}
// 重置模拟缓存
function clearMockCache(){
	$GLOBALS['mock_redis'] = [];
}
clearMockCache();
// 设置Redis连接配置（模拟环境下不影响测试）
container('config.redis', ['host' => '127.0.0.1', 'port' => 6379]);
// 设置缓存配置（部分测试用到配置名）
container('cache', [
	'用户资料' => ['APCu' => [3600, '_' => 'user_'], 'Redis' => [7200, '_' => 'user:']],
	'网站配置' => ['APCu' => [86400, '_' => 'config_'], 'Redis' => [43200, '_' => 'config:']],
]);
// ========== Redis 纯缓存测试（字符串/数组调用） ==========
test('Redis - 基本读写',
	cache(['fn' => 'Redis', 'key' => 'redis_test_key', 'ttl' => 60], fn($next) => 'redis测试数据'),
	'redis测试数据'
);
test('Redis - 验证数据已写入',
	cache(['fn' => 'Redis', 'key' => 'redis_test_key'], fn($next) => '不应该执行，应该从缓存读取'),
	'redis测试数据'
);
test('Redis - 使用配置名前缀',
	cache('用户资料', ['fn' => 'Redis', 'key' => 'user_456'], fn($next) => 'redis用户数据'),
	'redis用户数据'
);
// 预先写入一个特定key（用于后续非测试用途）
cache(['fn' => 'Redis', 'key' => 'redis_specific_key'], fn($next) => '特定的redis数据');
test('Redis - 缓存未命中时调用下一个',
	cache(['fn' => 'Redis', 'key' => 'not_exists_redis_key'], fn($next) => '新redis数据'),
	'新redis数据'
);
test('Redis - 复杂配置名',
	cache('网站配置', ['fn' => 'Redis', 'key' => 'complex_config'], fn($next) => '复杂配置数据'),
	'复杂配置数据'
);
// 准备持久化验证的数据
cache(['fn' => 'Redis', 'key' => 'redis_only_key'], fn($next) => 'redis数据');
test('混合场景 - 验证Redis数据持久化',
	cache(['fn' => 'Redis', 'key' => 'redis_only_key'], fn($next) => '不应该执行'),
	'redis数据'
);
test();