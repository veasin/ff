<?php
$srcDir = __DIR__ . '/../src';
$outputFile = __DIR__ . '/../dist/nx.php';
if (!is_dir(dirname($outputFile))) mkdir(dirname($outputFile), 0755, true);

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$namespaces = [];
$uses_fun = [];

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());

    $content = preg_replace('/^\s*<\?php\s*/', '', $content);
    $content = preg_replace('/^declare\(strict_types=1\);\s*/m', '', $content);

    if (!preg_match('/^namespace\s+([^;]+);/m', $content, $m)) continue;
    $ns = trim($m[1]);

    $content = preg_replace('/^namespace\s+[^;]+;\s*/m', '', $content);

    preg_match_all('/^use function\s+(.+?);\s*$/m', $content, $useLines);
    foreach ($useLines[1] as $line) {
        $line = trim($line);
        $isGroup = str_contains($line, '{') && $line[-1] === '}';
        if ($isGroup) {
            $bracePos = strpos($line, '{');
            $prefix = rtrim(trim(substr($line, 0, $bracePos)), '\\');
            $itemsStr = substr($line, $bracePos + 1, -1);
            $items = explode(',', $itemsStr);
        } else {
            $prefix = '';
            $items = [$line];
        }
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') continue;
            $alias = null;
            if (($asPos = stripos($item, ' as ')) !== false) {
                $alias = trim(substr($item, $asPos + 4));
                $item = trim(substr($item, 0, $asPos));
            }
            $fullName = ($prefix ? $prefix . '\\' : '') . $item;
            if ($alias) {
                $uses_fun[$ns][$fullName] = $alias;
            } elseif (!isset($uses_fun[$ns][$fullName])) {
                $uses_fun[$ns][$fullName] = null;
            }
        }
    }

    $content = preg_replace('/^use\s+.+?;\s*$/m', '', $content);

    $content = trim($content);
    if ($content === '') continue;

    $namespaces[$ns][] = $content;
}

$output = "<?php\n";
foreach ($namespaces as $ns => $codes) {
    $output .= "\nnamespace $ns {\n";
    if (isset($uses_fun[$ns]) && $uses_fun[$ns]) {
        $groups = [];
        foreach ($uses_fun[$ns] as $f => $alias) {
            $p = strrpos($f, '\\');
            if ($p === false) continue;
            $prefix = substr($f, 0, $p);
            $name = substr($f, $p + 1);
            $entry = $alias ? "$name as $alias" : $name;
            $groups[$prefix][] = $entry;
        }
        ksort($groups);
        foreach ($groups as $prefix => $names) {
            $names = array_unique($names);
            sort($names);
            $output .= "use function $prefix\\{" . implode(', ', $names) . "};\n";
        }
        $output .= "\n";
    }
    $output .= implode("\n\n", $codes) . "\n}\n";
}

//file_put_contents($outputFile."_.php", $output);
//echo "\033[32m{$outputFile}_.php\033[0m done.\n\n";

function generateVarName(bool $reset = false): string{
	static $chars = null;
	static $varNameLength = 1;
	static $varNameCounter = 0;
	// 初始化字符集（如果尚未初始化）
	if($chars === null){
		$chars = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
	}
	// 重置逻辑
	if($reset){
		$varNameLength = 1;
		$varNameCounter = 0;
		return ''; // 重置时不返回变量名
	}
	$result = '';
	$counter = $varNameCounter;
	$base = count($chars);
	// 转换为基于字符集的编号系统
	for($i = 0; $i < $varNameLength; $i++){
		$result = $chars[$counter % $base] . $result;
		$counter = intdiv($counter, $base);
	}
	// 更新计数器
	$varNameCounter++;
	if($varNameCounter >= pow($base, $varNameLength)){
		$varNameCounter = 0;
		$varNameLength++;
	}
	return $result;
}
function space($before = [], $after = []): bool{
	static $both = [
		T_DOUBLE_CAST,
		T_ELLIPSIS,//
		T_OBJECT_CAST,
		T_INT_CAST,
		T_DOUBLE_CAST,
		T_STRING_CAST,
		T_UNSET_CAST,//(int)
		T_INC,
		T_DEC,
		T_POW,//+ -
		T_DOUBLE_ARROW,
		//T_USE,
		T_OBJECT_OPERATOR,
		T_NULLSAFE_OBJECT_OPERATOR,// => use
		T_COALESCE,
		T_COALESCE_EQUAL,// ?? ??=
		T_CONCAT_EQUAL,
		T_DIV_EQUAL,
		T_MINUS_EQUAL,
		T_MOD_EQUAL,
		T_MUL_EQUAL,
		T_PLUS_EQUAL,
		T_POW_EQUAL,// +=
		T_IS_GREATER_OR_EQUAL,
		T_IS_EQUAL,
		T_IS_IDENTICAL,
		T_IS_NOT_EQUAL,
		T_IS_NOT_IDENTICAL,
		T_IS_SMALLER_OR_EQUAL,
		T_IS_IDENTICAL,// >=
		T_BOOLEAN_AND,
		T_BOOLEAN_OR,// && ||
		T_AND_EQUAL,
		T_OR_EQUAL,
		T_XOR_EQUAL,// &=
		T_SL,
		T_SL_EQUAL,
		T_SPACESHIP,
		T_SR,
		T_SR_EQUAL,// << <=> >> <<=
	];
	static $str = [':', '=', '<', '>', '+', '-', '*', '/', '?', ';', ','];
	[$id, $text] = $before;
	if(0 === $id) $b = !in_array($text, $str);
	else $b = !in_array($id, $both);
	[$id, $text] = $after;
	if(0 === $id) $a = !in_array($text, $str);
	else $a = !in_array($id, $both);
	return $a && $b;
}
function compress(string $content, $debug = false){
	$tokens = token_get_all($content);
	$outputParts = [];
	// 作用域栈 - 每个元素是一个作用域的变量映射表
	$scopeStack = [[]];  // 初始化为包含全局作用域
	$currentScopeIndex = 0;  // 当前作用域索引
	// 函数嵌套层级跟踪
	$functionNestingLevel = 0;
	// 代码块嵌套层级（包括函数、if、for等所有大括号）
	$blockNestingLevel = 0;
	$inFunctionParams = false;
	$startFunction = false;
	$currentFunctionParams = [];  // 当前函数的参数列表
	$passLine = 0;
	generateVarName(true);
	// 魔法变量列表（这些变量不混淆）
	$magicVars = ['GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV', 'argv', 'argc', 'http_response_header', 'php_errormsg', 'this'];
	$line = 1;
	$tokens = array_map(fn($t) => is_string($t) ? [0, $t] : $t, $tokens);
	// 遍历所有token
	$tokenCount = count($tokens);
	for($i = 0; $i < $tokenCount; $i++){
		$token = $tokens[$i];
		//if(is_string($token)) $token = [0, $token, $line];
		[$tid, $text,] = $token;
		$line = $token[2] ?? $line;
		if($line === $passLine) continue;
		if($debug) echo str_pad($line, 3, " ", STR_PAD_LEFT), "|", str_pad(0 === $tid ? "" : $tid |> token_name(...), 27, ' '), " '$text'", PHP_EOL;
		switch($tid){
			case T_OPEN_TAG:
			case T_DECLARE:
				if($debug) echo "> pass line: $line\n";
				$passLine = $line;
				break;
			case T_COMMENT:
			case T_DOC_COMMENT:
				break;
			case T_NAMESPACE:
				$outputParts[] = $text . "\n";
				break;
			case T_FUNCTION:
			case T_FN:  // 箭头函数
				// 增加函数嵌套层级
				$functionNestingLevel++;
				// 创建新的作用域
				$scopeStack[] = [];
				$currentScopeIndex = count($scopeStack) - 1;
				// 开始解析函数参数
				$startFunction = true;
				$currentFunctionParams = [];
				$outputParts[] = $text;
				break;
			case T_VARIABLE:
				$outputParts[] = $text;
				continue 2;
				if($debug) $varName = substr($text, 1);
				echo "> ($currentScopeIndex) ", $varName, " => ";
				// 检查是否是魔法变量或$this
				if(in_array($varName, $magicVars)){
					if($debug) echo "Magic\n";
					$outputParts[] = $text;
					break;
				}
				// 检查是否在函数参数列表中
				if($inFunctionParams){
					// 记录函数参数，但不混淆
					$currentFunctionParams[] = $varName;
					$outputParts[] = $text;
					if($debug) echo "Params\n";
					break;
				}
				// 检查是否是函数参数（已经被记录的）
				if(!empty($currentFunctionParams) && in_array($varName, $currentFunctionParams)){
					if($debug) echo "Params Exist\n";
					$outputParts[] = $text;
					break;
				}
				// 普通变量：在当前作用域及上层作用域中查找
				$mappedName = null;
				for($j = $currentScopeIndex; $j >= 0; $j--){
					if(isset($scopeStack[$j][$varName])){
						$mappedName = $scopeStack[$j][$varName];
						break;
					}
				}
				if($mappedName === null){
					// 生成新的变量名
					$newVarName = generateVarName();
					$scopeStack[$currentScopeIndex][$varName] = $newVarName;
					$mappedName = $newVarName;
					if($debug) echo "NEW($currentScopeIndex) ";
				}
				echo "$mappedName\n";
				$outputParts[] = '$' . $mappedName;
				break;
			case T_WHITESPACE:
				 //压缩代码：删除换行和多余空格
				if(trim($text) === '' && str_contains($text, "\n")){
					$outputParts[] = '';
					continue 2;
				}
				if(space($tokens[$i - 1] ?? [], $tokens[$i + 1] ?? [])) $outputParts[] = $text;
				break;
			case 0:
				//echo "  -", str_pad("|", 27, " "), " '$token'", PHP_EOL;
				// 处理单个字符
				$char = $text;
				switch($char){
					case "(":
						if($functionNestingLevel > 0 && $startFunction){
							if($debug) echo "----- in params\n";
							// 已经在函数中，开始参数列表
							$inFunctionParams = true;
						}
						break;
					case ")":
						if($functionNestingLevel > 0 && $startFunction){
							if($debug) echo "----- end params\n";
							// 参数列表结束
							$inFunctionParams = false;
							$startFunction = false;
						}
						break;
					case "{":
						if($functionNestingLevel > 0 && $startFunction){
							// 函数体开始，参数列表已经结束
							if($debug) echo "----- end params\n";
							$inFunctionParams = false;
							$startFunction = false;
						}
						$blockNestingLevel++;
						break;
					case "}":
						$blockNestingLevel--;
						// 检查是否是函数结束
						// 当函数嵌套层级大于0，并且代码块嵌套层级等于函数嵌套层级时，
						// 表示这是函数的最外层大括号关闭
						if($functionNestingLevel > 0 && $blockNestingLevel === $functionNestingLevel - 1){
							if($debug) echo "----- function end (nesting: $functionNestingLevel, block: $blockNestingLevel)\n";
							// 函数结束：弹出作用域
							$functionNestingLevel--;
							// 弹出当前函数的作用域
							if(count($scopeStack) > 1){  // 保留全局作用域
								array_pop($scopeStack);
								$currentScopeIndex = count($scopeStack) - 1;
							}
							// 清空当前函数参数
							$currentFunctionParams = [];
						}
						break;
				}
				$outputParts[] = $char;
				break;
			default:
				$outputParts[] = $text;
				break;
		}
	}
	return implode('', $outputParts);
}

$content = compress($output);
file_put_contents($outputFile, "<?php\n$content");

echo "\n\n\033[32m{$outputFile}\033[0m done.";
