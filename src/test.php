<?php
declare(strict_types=1);
namespace ff;
/**
 * 轻量级测试函数，支持直接比较、闭包断言和异常断言。
 * 0 参触发：test() 执行所有测试并返回结果数组，不输出
 * null 清空：test(null) 清空已注册的测试用例
 * ```
 * test('数字比较', 5, 5);                     // 直接比较
 * test('加法', fn() => 2+2, 4);               // value 是闭包
 * test('范围判断', 10, fn($v) => $v > 5);     // assign 是断言函数
 * test('除零类型', fn() => 1/0, DivisionByZeroError::class);  // 异常类型断言
 * test('除零', fn() => 1/0, fn($v) => $v instanceof DivisionByZeroError); // 闭包处理异常
 * $result = test();                           // 执行并返回 ['total'=>int, 'passed'=>int, 'failed'=>array]
 * test(null);                                 // 清空所有测试用例
 * ```
 * 闭包或异常类型字符串传入 assign 时自动匹配；value/assign 执行中抛出异常被捕获后
 * 以异常对象形式参与比较（异常 vs 具体值必然失败）。
 * 输出由扩展包负责，CLI 渲染使用 ff\enable('cli-test')。
 * @param string|null $label  测试用例的标识名称，不传时执行所有测试并返回结果；null 时清空
 * @param mixed       $value  待测试的值。传入闭包则取其返回值；闭包内抛异常则被捕获
 * @param mixed       $assign 预期值、异常类名或断言闭包。断言闭包接收 actual 返回 bool
 * @return ?array 0 参执行时返回结果数组，其余情况返回 null
 */
function test(?string $label = null, mixed $value = null, mixed $assign = null): ?array{
	static $cases = [];
	if(0 === func_num_args()){
		$total = count($cases);
		$passed = 0;
		$failed = [];
		foreach($cases as [$label, $value, $assign]){
			try { $actual = is_callable($value) ? $value() : $value; }
			catch(\Throwable $e) { $actual = $e; }
			try {
				$expect = match(true){
					is_callable($assign) => $assign($actual),
					is_string($assign) && $actual instanceof \Throwable && is_a($assign, \Throwable::class, true) => $actual instanceof $assign,
					default => $actual === $assign,
				};
			} catch(\Throwable $e) { $expect = $e; }
			if($expect === true) $passed++;
			else $failed[] = ['label' => $label, 'expected' => $assign, 'actual' => $actual];
		}
		$cases = [];
		$result = ['total' => $total, 'passed' => $passed];
		if($failed) $result['failed'] = $failed;
		return $result;
	}
	if(func_num_args() === 1 && $label === null){
		$cases = [];
		return null;
	}
	$cases[] = [$label, $value, $assign];
	return null;
}
