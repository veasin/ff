<?php
declare(strict_types=1);
namespace nx;
/**
 * 安全调用：封装 try/catch，失败返回 null。
 * ```
 * safe(fn() => db('SELECT * FROM users'));                        // 无参
 * safe(fn($id) => db('SELECT * FROM users WHERE id=?', [$id]), 1);// 单参
 * safe(fn($a, $b) => $a / $b, 10, 0);                            // 多参
 * ```
 * @param callable $fn 要安全调用的函数
 * @param mixed ...$args 传递给函数的参数
 * @return mixed 函数返回值，失败返回 null
 */
function safe(callable $fn, mixed ...$args): mixed{
	try{
		return $fn(...$args);
	}catch(\Throwable){
		return null;
	}
}
