<?php
namespace nx;
use Throwable;

/**
 * 轻量级测试函数，支持直接比较和闭包断言。
 * ```
 * test('数字比较', 5, 5);                    // 直接比较
 * test('函数返回值', fn() => 2+2, 4);         // value是函数
 * test('范围判断', 10, fn($v) => $v > 5);     // assign是断言函数
 * test('数组验证', ['a' => 1], function($value) {
 *     return isset($value['a']) && $value['a'] === 1;
 * });
 * test();                                     // 执行所有测试并输出
 * ```
 * CLI 下彩色输出，非 CLI 下纯文本输出。
 * @param string|null $label  测试用例的标识名称，不传时执行所有测试并输出
 * @param mixed       $value  待测试的值。如果是闭包，则取返回值
 * @param mixed       $assign 预期值或断言函数。如果是闭包，接收实际值返回 bool
 * @return void
 */
function test(?string $label = null, mixed $value = null, mixed $assign = null): void{
	static $colors = [32, 31, 33, 90];
	static $c = fn(string $text, int $color = 0) => PHP_SAPI === 'cli' ? "\033[$colors[$color]m$text\033[0m" : $text;
	static $cases = [];
	if(0 === func_num_args()){
		$total = count($cases);
		$passed = 0;
		$failed = [];
		foreach($cases as [$label, $value, $assign]){
			try{
				$actual = is_callable($value) ? $value() : $value;
				$expect = is_callable($assign) ? $assign($actual) : $actual === $assign;
				if($expect === true) $passed++;
				else $failed[] = [$label, $actual, $assign];
			} catch(Throwable $e){
				$failed[] = [$label, $e->getMessage(), $assign];
			}
		}
		if(empty($failed)) echo $c("✔ 全部通过") . $c(": ", 2) . $c($passed) . $c("/$total", 2) . "\n";
		else{
			foreach($failed as [$label, $actual, $expect]){
				echo $c("▶ {$label}", 1) . "\n";
				echo "\t" . $c("预期:", 3) . "\t" . json_encode($expect, JSON_UNESCAPED_UNICODE) . "\n";
				echo "\t" . $c("实际:", 3) . "\t" . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
			}
			echo $c("● 测试失败", 1) . $c(": ", 2) . $c(count($failed), 1) . $c(", ", 2) . $c($passed) . $c("/$total", 2) . "\n";
		}
		$cases =[];
		return;
	}
	$cases[] = [$label, $value, $assign];
}
