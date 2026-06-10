<?php
namespace nx;
/**
 * 轻量级测试函数，支持直接比较、闭包断言和异常断言。
 * 0 参触发：test() 执行所有测试并输出
 * null 清空：test(null) 清空已注册的测试用例
 * ```
 * test('数字比较', 5, 5);                     // 直接比较
 * test('加法', fn() => 2+2, 4);               // value 是闭包
 * test('范围判断', 10, fn($v) => $v > 5);     // assign 是断言函数
 * test('除零类型', fn() => 1/0, DivisionByZeroError::class);  // 异常类型断言
 * test('除零', fn() => 1/0, fn($v) => $v instanceof DivisionByZeroError); // 闭包处理异常
 * test();                                     // 执行所有测试并输出
 * test(null);                                 // 清空所有测试用例
 * ```
 * 闭包或异常类型字符串传入 assign 时自动匹配；value/assign 执行中抛出异常被捕获后
 * 以异常对象形式参与比较（异常 vs 具体值必然失败），输出时显示异常 message。
 *
 * 输出使用 i18n 格式字符串，支持 [字母] 颜色标记：
 * - 小写=标准色：k黑 r红 g绿 y黄 b蓝 m品 c青 w白 n灰(亮黑)
 * - 大写=亮色：K亮黑 R亮红 G亮绿 Y亮黄 B亮蓝 M亮品 C亮青 W亮白
 * - [r:w] 前景红底白，[ :] 重置前景，[: ] 重置背景，[ : ] 重置全部，[:] 重置全部简写
 * @param string|null $label  测试用例的标识名称，不传时执行所有测试并输出；null 时清空
 * @param mixed       $value  待测试的值。传入闭包则取其返回值；闭包内抛异常则被捕获
 * @param mixed       $assign 预期值、异常类名或断言闭包。断言闭包接收 actual 返回 bool
 * @return void
 */
function test(?string $label = null, mixed $value = null, mixed $assign = null): void{
	static $render = function(string $text): string{
		$match = '/\[([krgybmcwnKRGYBMCW ]?)(:?)([krgybmcwnKRGYBMCW ]?)]/';
		if(!container('#mode:cli')) return preg_replace($match, '', $text);
		return preg_replace_callback($match, static function($m){
			$fg = ['k' => 30, 'r' => 31, 'g' => 32, 'y' => 33, 'b' => 34, 'm' => 35, 'c' => 36, 'w' => 37, 'n' => 90, 'K' => 90, 'R' => 91, 'G' => 92, 'Y' => 93, 'B' => 94, 'M' => 95, 'C' => 96, 'W' => 97, ' ' => 39];
			$bg = ['k' => 40, 'r' => 41, 'g' => 42, 'y' => 43, 'b' => 44, 'm' => 45, 'c' => 46, 'w' => 47, 'K' => 100, 'R' => 101, 'G' => 102, 'Y' => 103, 'B' => 104, 'M' => 105, 'C' => 106, 'W' => 107, ' ' => 49];
			if($m[1] === '' && $m[2] === ':' && $m[3] === '') return "\033[39;49m";
			$codes = array_filter([$fg[$m[1]] ?? null, $m[2] === ':' ? ($bg[$m[3]] ?? null) : null]);
			return $codes ? "\033[" . implode(';', $codes) . "m" : '';
		}, $text) . "\033[0m";
	};
	static $cases = [];
	static $shutdown = false;
	if(0 === func_num_args()){
		if(empty($cases)) return;
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
			else $failed[] = [$label, $actual, $assign];
		}
		if(empty($failed)) echo $render(i18n('#test:passed', ['passed' => $passed, 'total' => $total])) , "\n";
		else{
			foreach($failed as [$label, $actual, $assign]){
				$actualOut = $actual instanceof \Throwable ? $actual->getMessage() : json_encode($actual, JSON_UNESCAPED_UNICODE);
				if($assign instanceof \Throwable) $expectOut = $assign->getMessage();
				elseif(is_callable($assign)) $expectOut = 'assertion';
				else $expectOut = json_encode($assign, JSON_UNESCAPED_UNICODE);
				echo $render(i18n('#test:case', ['label' => $label, 'expected' => $expectOut, 'actual' => $actualOut,])) , "\n";
			}
			echo $render(i18n('#test:failed', ['count' => count($failed), 'passed' => $passed, 'total' => $total])) , "\n";
		}
		$cases = [];
		return;
	}
	if(func_num_args() === 1 && $label === null){
		$cases = [];
		return;
	}
	if(!$shutdown && !container('#mode:worker')){
		$shutdown = true;
		register_shutdown_function(fn() => test());
	}
	$cases[] = [$label, $value, $assign];
}
