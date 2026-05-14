<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{filter, test};

// 测试 int 类型转换
test('int 转换', filter('123', 'int'), 123);
// 测试 str 类型转换
test('str 转换', filter(123, 'str'), '123');
// 测试 email 验证
test('email 验证通过', filter('test@example.com', 'email'), 'test@example.com');
// 测试无效 email
test('无效 email 返回 null', filter('invalid-email', 'email'), null);
// 测试 url 验证
test('url 验证通过', filter('https://example.com', 'url'), 'https://example.com');
// 测试 number 验证 >0
test('number >0 验证通过', filter(5, '>0'), 5);
// 测试 number 验证 <10
test('number <10 验证通过', filter(5, '<10'), 5);
// 测试无效 number 验证
test('number >10 返回 null', filter(5, '>10'), null);
// 测试 json 解析
test('json 解析通过', filter('{"key":"value"}', 'json'), ['key' => 'value']);
// 测试无效 json
test('无效 json 返回 null', filter('invalid json', 'json'), null);
// 测试 bool 转换 true
test('bool 转换 true', filter('true', 'bool'), true);
// 测试 bool 转换 false
test('bool 转换 false', filter('no', 'bool'), false);
// 测试多规则验证
test('多规则验证', filter('123', 'int', '>0', '<1000'), 123);
// 测试规则组合
test('规则组合验证', filter(5, 'number,>0,<10'), 5);
// 测试复杂场景：数字范围验证
test('数字范围验证', filter(50, 'int', '>=0', '<=100'), 50);
// 测试布尔值各种表示
test('bool 转换 yes', filter('yes', 'bool'), true);
test('bool 转换 on', filter('on', 'bool'), true);
test('bool 转换 off', filter('off', 'bool'), false);
test('bool 转换 1', filter(1, 'bool'), true);
test('bool 转换 0', filter(0, 'bool'), false);
test();

