<?php
include __DIR__ . "/../_boot.php";

use function nx\test;

$config = ['APCu' => [3600, '_' => 'test:']];
$extraConfig = null;
$keyConfigName = 'user';

$next = fn() => 'computed_value';

$result = \nx\cache\apcu($next, $keyConfigName, $config, $extraConfig);
test('cache_apcu 未命中时调用next', $result, 'computed_value');

$config2 = ['APCu' => [60, '_' => '']];
apcu_store('test_key', 'cached_value', 60);
$result2 = \nx\cache\apcu(null, 'test_key', $config2, null);
test('cache_apcu 命中缓存', $result2, 'cached_value');

apcu_delete('test_key');

test();