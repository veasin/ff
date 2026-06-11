<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{cache, container, test};
use function nx\cache\redis;

// Redis 中间件工厂测试
// 无 Redis 服务器时，中间件自动回退到 $next() 进行计算

container('config.redis', ['host' => '127.0.0.1', 'port' => 6379]);

test('未命中时计算并存储',
	cache(redis('test_key', middleware: ['ttl' => 60]), fn($next) => 'computed'),
	'computed'
);
test('不同键不命中',
	cache(redis('other_key', middleware: true), fn($next) => 'fresh'),
	'fresh'
);
test('多级链中缓存中间件',
	cache(redis('chain_key', middleware: ['ttl' => 60]), fn($next) => 'chain_value'),
	'chain_value'
);
test('null 值不被缓存',
	cache(redis('null_test', middleware: true), fn($next) => null),
	null
);
test('factory TTL 简写 int',
	cache(redis('ttl_key', middleware: 60), fn($next) => 'ttl_value'),
	'ttl_value'
);

test();
