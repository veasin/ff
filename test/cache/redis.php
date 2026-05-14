<?php
include __DIR__ . "/../../vendor/autoload.php";

use function nx\{container, test};

test('cache_redis 跳过测试', true, true);
test();