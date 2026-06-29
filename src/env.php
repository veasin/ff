<?php
declare(strict_types=1);
namespace ff;
/**
 * 获取环境变量值，支持系统环境变量、$_ENV、.env 文件三种来源。
 * 自动类型转换：'true'/'false'/'null'/'empty' 转为对应类型。
 * .env 文件路径通过 container('#env') 配置，默认自动从 src/ 向上搜索。
 * ```
 * $debug = env('APP_DEBUG');     // 自动类型转换
 * $host  = env('DB_HOST');       // 不存在返回 null
 * $name  = env('APP_NAME');      // 字符串原样返回
 * ```
 * @param string $name 环境变量名
 * @return mixed 变量值，不存在返回 null
 */
function env(string $name): mixed{
	$cast = fn($v) => match (strtolower($v)) {
		'true' => true,
		'false' => false,
		'null' => null,
		'empty' => '',
		default => $v,
	};
	$value = getenv($name);
	if($value !== false) return $cast($value);
	if(array_key_exists($name, $_ENV)) return $cast($_ENV[$name]);
	static $parsed = null;
	if($parsed === null){
		$parsed = [];
		$path = container('#env');
		if($path === null){
			$dir = __DIR__;
			for($i = 0; $i < 3; $i++){
				$candidate = $dir . '/.env';
				if(is_file($candidate)){
					$path = $candidate;
					break;
				}
				$parent = dirname($dir);
				if($parent === $dir) break;
				$dir = $parent;
			}
		}
		if($path !== null){
			$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach($lines as $line){
				$line = trim($line);
				if($line === '' || $line[0] === '#') continue;
				if(str_starts_with($line, 'export ')) $line = substr($line, 7);
				$pos = strpos($line, '=');
				if($pos === false) continue;
				$key = trim(substr($line, 0, $pos));
				$v = trim(substr($line, $pos + 1));
				if(strlen($v) > 1){
					$first = $v[0];
					$last = $v[-1];
					if(($first === '"' && $last === '"') || ($first === "'" && $last === "'")) $v = substr($v, 1, -1);
				}
				$parsed[$key] = $cast($v);
			}
		}
	}
	return $parsed[$name] ?? null;
}
