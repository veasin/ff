<?php
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{middleware, test, container};
use function ff\middleware\prefab\error;

test('error: 正常执行返回结果', function() {
    return middleware(error(), 'ok');
}, 'ok');

test('error: 默认异常 message=空', function() {
    container('#out.response', null);
    middleware(error(), function() {
        throw new \RuntimeException('custom msg');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 500 && $r['message'] === '';
}, true);

test('error: int 配置 message=空', function() {
    container('#out.response', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \InvalidArgumentException('参数错误');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 400 && $r['message'] === '';
}, true);

test('error: int + container 后备', function() {
    container('#mw/error/400', '错误的请求');
    container('#out.response', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \InvalidArgumentException('参数错误');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 400 && $r['message'] === '错误的请求';
}, true);

test('error: {message} 替换为 $e->getMessage()', function() {
    container('#out.response', null);
    middleware(error([\InvalidArgumentException::class => [400, '{message}']]), function() {
        throw new \InvalidArgumentException('参数值不合法');
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 400 && $r['message'] === '参数值不合法';
}, true);

test('error: i18n 上下文格式化', function() {
    container('i18n.zh_CN.#error:with_line', '第 {line} 行出错');
    container('#out.response', null);
    middleware(error([\LengthException::class => [400, '#error:with_line']]), function() {
        throw new \LengthException('too long', 0);
    });
    $r = container('#out.response');
    container('#out.response', null);
    return $r['code'] === 400 && str_contains($r['message'] ?? '', '行');
}, true);

test('error: 未匹配走默认 500', function() {
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
    return $r['code'] === 422 && $r['message'] === '领域错误';
}, true);

test();

