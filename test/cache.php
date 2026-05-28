<?php
include __DIR__ . "/_boot.php";

use function nx\{cache, container, test};

// 设置测试配置（仅用于配置名传递，不影响无缓存处理器的测试）
container('cache', [
	'用户资料' => ['APCu' => [3600, '_' => 'user_'], 'Redis' => [7200, '_' => 'user:']],
	'商品详情' => ['APCu' => [1800, '_' => 'prod_']],
	'网站配置' => ['APCu' => [86400, '_' => 'config_'], 'Redis' => [43200, '_' => 'config:']],
]);
// 测试1: 基本调用 - 单个闭包直接返回数据
test('缓存基本功能 - 单个闭包直接返回数据', cache(fn($next) => '测试值'), '测试值');
// 测试2: 多个闭包，第一个返回null，第二个返回数据
test('缓存链 - 第一个返回null，第二个返回数据', cache(fn($next) => $next(), fn($next) => '第二个数据'), '第二个数据');
// 测试3: 链式调用，所有都返回null
test('缓存链 - 所有都返回null', cache(fn($next) => $next(), fn($next) => $next(), fn($next) => null), null);
// 测试4: 使用key配置名
test('缓存使用配置名', cache('用户资料', fn($next, $keyConfig, $config) => $keyConfig . ':' . ($config['APCu'][0] ?? ''), fn($next) => '不应该执行到这里'), '用户资料:3600');
// 测试5: 数组格式配置（不依赖实际存储，APCu不可用则回退到闭包）
test('缓存使用数组配置', cache(['fn' => 'APCu', 'ttl' => 1800, 'key' => '测试键'], fn($next) => '数组配置值'), '数组配置值');
// 测试6: 复杂配置 - 带默认值的链式调用
$log = [];
$result = cache(function($next) use (&$log){
	$log[] = '第一个';
	return $next();
},
	function($next) use (&$log){
	$log[] = '第二个';
	return $next();
}, function($next) use (&$log){
	$log[] = '第三个';
	return '最终值';
});
test('缓存复杂链式调用', ['结果' => $result, '调用记录' => $log], ['结果' => '最终值', '调用记录' => ['第一个', '第二个', '第三个']]);
// 测试7: 带写入缓存逻辑的闭包
$storage = [];
test('缓存带写入逻辑',
	cache(function($next) use (&$storage){
		$value = $next();
		if($value !== null){
			$storage['已缓存'] = $value;
		}
		return $value;
	}, function($next){
		return '写入缓存的数据';
	}),
	'写入缓存的数据'
);
// 测试8: 中间缓存返回null，继续向下
test('缓存中间返回null继续执行', cache(fn($next) => $next(), fn($next) => null, fn($next) => '最终数据'), '最终数据');
// 测试9: 无缓存函数
test('缓存无函数', cache(), null);
// 测试10: 无效的缓存方法（会被跳过）
test('缓存无效方法被跳过', cache('无效字符串', fn($next) => '有效数据'), '有效数据');
// 测试11: 使用配置名的复杂场景
$context = [];
$result = cache('商品详情',
	function($next, $keyConfig, $config) use (&$context){
	$context['键名'] = $keyConfig;
	$context['配置'] = $config;
	return $next();
}, function($next) use (&$context){
	$context['第二个调用'] = true;
	return '商品值';
});
test('缓存使用配置名复杂场景', ['结果' => $result, '上下文' => $context,], ['结果' => '商品值', '上下文' => ['键名' => '商品详情', '配置' => ['APCu' => [1800, '_' => 'prod_'],], '第二个调用' => true,],]);
// 测试12: 多个数组配置混合（不依赖存储，APCu/Redis不可用则回退）
test('缓存混合数组配置', cache(['fn' => 'APCu', 'ttl' => 300, 'key' => '键1'], ['fn' => 'Redis', 'ttl' => 600, 'key' => '键2'], fn($next) => '混合值'), '混合值');
// 测试13: 短路返回
test('缓存短路返回', cache(fn($next) => '立即返回', fn($next) => '不应该执行'), '立即返回');
// 测试14: 使用默认值模式（通过??）
$value = cache(fn($next) => $next(), fn($next) => null) ?? '默认值';
test('缓存使用默认值', $value, '默认值');
// 测试15: 闭包接收额外参数
test('缓存闭包接收所有参数',
	cache('用户资料', function($next, $keyConfig, $config){ return json_encode(['键名' => $keyConfig, '有配置' => !empty($config),], JSON_UNESCAPED_UNICODE); }),
	'{"键名":"用户资料","有配置":true}'
);
// 测试16: 多层嵌套调用
test('缓存多层嵌套', cache(fn($next) => '外层_' . cache(fn($innerNext) => '内层_' . ($innerNext ? $innerNext() : '无下一级'), fn($next) => '数据')), '外层_内层_数据');
// 测试17: 空数组作为缓存方法
test('缓存空数组作为方法', cache([], fn($next) => '空数组之后'), '空数组之后');
// 测试18: 多个配置名参数（第一个是配置名，后面的都是方法）
test('缓存多个配置名参数', cache('用户资料', fn($next, $key) => '第一个_' . $key, fn($next) => '不应该执行'), '第一个_用户资料');
// 测试29: 多个缓存方法混合使用（无命中，仅验证链式调用）
test('缓存多个缓存方法混合使用', cache(['fn' => 'APCu', 'key' => 'mixed_apcu_key'], ['fn' => 'Redis', 'key' => 'mixed_redis_key'], fn($next) => '混合缓存数据'), '混合缓存数据');
// 测试34: 所有缓存都未命中，最终获取数据
test('混合场景 - 所有缓存都未命中，最终获取数据', cache(['fn' => 'APCu', 'key' => 'all_miss_key1'], ['fn' => 'Redis', 'key' => 'all_miss_key2'], fn($next) => '最终获取的数据'), '最终获取的数据');
// 测试35: 带配置名的完整链路（无命中）
test('混合场景 - 带配置名的完整链路',
	cache('商品详情', ['fn' => 'APCu', 'key' => 'product_789'], ['fn' => 'Redis', 'key' => 'product_789'], fn($next, $keyConfig) => '商品数据:' . $keyConfig),
	'商品数据:商品详情'
);
test();