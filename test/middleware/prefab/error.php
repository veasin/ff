<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function nx\{middleware, test, container};
use function nx\middleware\prefab\error;

test('error: 正常执行返回结果', function() {
    return middleware(error(), 'ok');
}, 'ok');

test('error: 默认异常 body=null', function() {
    container('#out.response', null);
    middleware(error(), function() {
        throw new \RuntimeException('custom msg');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 500 && $r['body'] === null;
}, true);

test('error: int 配置无 body', function() {
    container('#out.response', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \InvalidArgumentException('参数错误');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 400 && $r['body'] === null;
}, true);

test('error: int 配置 + container 后备', function() {
    container('#error:400', '#error:bad_request');
    container('i18n.zh_CN.#error:bad_request', '错误的请求');
    container('#out.response', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \InvalidArgumentException('参数错误');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 400 && $r['body']['error'] === '错误的请求';
}, true);

test('error: [code, msg] 使用 i18n', function() {
    container('i18n.zh_CN.#error:not_found', '资源不存在');
    container('#out.response', null);
    middleware(error([\OutOfRangeException::class => [404, '#error:not_found']]), function() {
        throw new \OutOfRangeException('not found');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 404 && $r['body']['error'] === '资源不存在';
}, true);

test('error: i18n 上下文格式化', function() {
    container('i18n.zh_CN.#error:with_line', '错误发生在 {line} 行');
    container('#out.response', null);
    middleware(error([\LengthException::class => [400, '#error:with_line']]), function() {
        throw new \LengthException('too long', 0);
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 400 && str_contains($r['body']['error'] ?? '', '行');
}, true);

test('error: 未匹配的异常走默认 500', function() {
    container('#out.response', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \RuntimeException('unexpected');
    });
    $v = container('#out.response.code');
    container('#out.response', null);
    return $v;
}, 500);

test('error: 多异常类型映射', function() {
    container('i18n.zh_CN.#error:domain', '领域错误');
    container('#out.response', null);
    middleware(error([
        \InvalidArgumentException::class => 400,
        \DomainException::class => [422, '#error:domain'],
    ]), function() {
        throw new \DomainException('domain error');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 422 && $r['body']['error'] === '领域错误';
}, true);

test();

