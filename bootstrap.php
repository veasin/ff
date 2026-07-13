<?php
// bootstrap.php — ff core 自注册（via ext 系统）
use function ff\{container, hook, output};

container([
	'^#ext' => [
		'from' => [
			'body' => \ff\from\body(...),
			'header' => \ff\from\header(...),
		],
		'http' => [
			'curl' => \ff\http\curl(...),
			'stream' => \ff\http\stream(...),
		],
		'queue' => [
			'db' => \ff\queue\db(...),
		],
		'out' => [
			'type' => [
				'json' => \ff\output\type\json(...),
				'view' => \ff\output\type\view(...),
				'file' => \ff\output\type\file(...),
			],
			'emit' => [
				'http' => \ff\output\http(...),
				'cli' => \ff\output\cli(...),
			],
		],
	],
	'^#input' => [
		'type' => [
			'int' => fn($v) => is_numeric($v) ? (int)$v : null,
			'uint' => fn($v) => (is_numeric($v) && $v >= 0) ? (int)$v : null,
			'str' => fn($v) => (string)$v,
			'bool' => fn($v) => (bool)$v,
			'float' => fn($v) => is_numeric($v) ? (float)$v : null,
			'array' => fn($v) => !is_array($v) ? (str_contains((string)$v, ',') ? explode(',', $v) : [$v]) : $v,
			'json' => fn($v) => json_decode((string)$v, true) ?: null,
			'date' => fn($v) => strtotime((string)$v) ?: null,
			'hex' => fn($v) => hexdec(trim((string)$v)),
			'base64' => fn($v) => base64_decode((string)$v, true) ?: null,
		],
		'check' => [
			'cmp' => function($v, $p){
				$left = is_numeric($v) ? $v : strlen($v);
				return match ($p[0]) {
					'=' => $left == $p[1],
					'!=' => $left != $p[1],
					'>' => $left > $p[1],
					'<' => $left < $p[1],
					'>=' => $left >= $p[1],
					'<=' => $left <= $p[1],
					default => ['throw', ['message' => "cmp: unknown operator $p[0]"]],
				};
			},
			'range' => function($v, $p){
				$left = is_numeric($v) ? $v : strlen($v);
				return $left >= $p[0] && $left <= $p[1];
			},
			'enum' => fn($v, $p) => in_array($v, $p, true),
			'regex' => fn($v, $p) => preg_match($p, $v) === 1,
			'filter' => fn($v, $p) => filter_var($v, $p[0], $p[1] ?? 0) !== false,
			'number' => is_numeric(...),
		],
		'abbr' => [
			'body' => ['from' => 'body'],
			'query' => ['from' => 'query'],
			'header' => ['from' => 'header'],
			'cookie' => ['from' => 'cookie'],
			'file' => ['from' => 'file'],
			'params' => ['from' => 'params'],
			'remove' => ['null' => 'remove'],
			'throw' => ['null' => 'throw'],
			'email' => ['filter' => [FILTER_VALIDATE_EMAIL]],
			'url' => ['filter' => [FILTER_VALIDATE_URL]],
			'ip' => ['filter' => [FILTER_VALIDATE_IP]],
			'ip-v6' => ['filter' => [FILTER_VALIDATE_IP, FILTER_FLAG_IPV6]],
			'uuid' => ['regex' => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'],
		],
		'parse' => [
			'/^(>=?|<=?|!=|=)(\d+(?:\.\d+)?)$/' => fn($m) => ['cmp' => [$m[1], (float)$m[2]]],
			'/^(.+(\|.+)+)$/' => fn($m) => ['enum' => explode('|', $m[1])],
			'/^regex:(.+)$/' => fn($m) => ['regex' => $m[1]],
			'/^(\d+)\.\.(\d+)$/' => fn($m) => ['range' => [(int)$m[1], (int)$m[2]]],
			'/^(\d+)-(\d+)$/' => fn($m) => ['range' => [(int)$m[1], (int)$m[2]]],
		],
	],
	'^#out' => ['type' => 'json', 'emit' => container('#mode:cli') ? 'cli' : 'http'],
]);
hook('after', output(...));
register_shutdown_function(hook(...));
