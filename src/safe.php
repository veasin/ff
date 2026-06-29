<?php
declare(strict_types=1);
namespace ff;
/**
 * 安全调用：封装 try/catch，失败返回 null。
 * 可通过容器注册异常处理器：
 * ```
 * safe(fn() => db('SELECT * FROM users'));                        // 无参
 * safe(fn($id) => db('SELECT * FROM users WHERE id=?', [$id]), 1);// 单参
 * safe(fn($a, $b) => $a / $b, 10, 0);                            // 多参
 * container('#safe', fn(\Throwable $e) => match(true){            // 注册异常处理器
 *     $e instanceof \PDOException => ['err' => 'db', 'msg' => $e->getMessage()],
 *     default => null,
 * });
 * ```
 * @param callable $fn 要安全调用的函数
 * @param mixed ...$args 传递给函数的参数
 * @return mixed 函数返回值，失败返回 null 或异常处理器的返回值
 */
function safe(callable $fn, mixed ...$args): mixed{
	try{
		return $fn(...$args);
	}catch(\Throwable $e){
		return is_callable($handler = container('#safe')) ? $handler($e) : null;
	}
}
