<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\env;
use function ff\test;

// 准备 .env 文件
file_put_contents(__DIR__ . '/../.env', <<<EOT
DB_HOST=localhost
APP_DEBUG=true
APP_NULL=null
APP_EMPTY=empty
#comment=skip
export SECRET_KEY=abc123
PASSWORD="my_pass"
NAME='John Doe'
EOT
);

test('.env值', env('DB_HOST'), 'localhost');
test('true转为bool', env('APP_DEBUG'), true);
test('null转为null', env('APP_NULL'), null);
test('empty转为空串', env('APP_EMPTY'), '');
test('跳过注释', env('comment'), null);
test('默认值', env('NOT_EXIST') ?? 'fallback', 'fallback');
test('export前缀', env('SECRET_KEY'), 'abc123');
test('双引号值', env('PASSWORD'), 'my_pass');
test('单引号值', env('NAME'), 'John Doe');

// 服务器环境变量优先级高于 .env
putenv('DB_HOST=from_server');
test('服务器变量优先级', env('DB_HOST'), 'from_server');
putenv('DB_HOST');

test();
// 清理
unlink(__DIR__ . '/../.env');
