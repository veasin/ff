<?php
declare(strict_types=1);
namespace nx;
/**
 * @param string $name
 * @return mixed
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
