<?php
include __DIR__ . "/../_boot.php";

use function nx\{container, test};

test('cache_redis 跳过测试', true, true);
test();