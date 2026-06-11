<?php
include __DIR__ . "/../../vendor/autoload.php";

use function nx\{container, test};
use function nx\cache\redis;

// Redis CRUD 驱动需运行中的 Redis 服务器才能验证完整读写。
// 无服务器时驱动优雅降级，所有操作返回 null。
// 完整测试需要: 启动 Redis 服务 + 设置 container('config.redis', [...])

container('config.redis', ['host' => '127.0.0.1', 'port' => 6379]);

test('无参返回 null', redis(), null);
test('无服务时读取返回 null', redis('nonexistent'), null);
test('无服务时写入返回 null', redis('k', 'v'), null);
test('无服务时删除返回 null', redis('k', null), null);
test('无服务时清空返回 null', redis(null), null);
test('无服务时批量读返回 null', redis(['a']), null);
test('无服务时批量写返回 null', redis(['a' => '1']), null);

test();
