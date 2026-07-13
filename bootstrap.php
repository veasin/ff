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
				return match ($p['op']) {
					'=' => $left == $p['value'],
					'!=' => $left != $p['value'],
					'>' => $left > $p['value'],
					'<' => $left < $p['value'],
					'>=' => $left >= $p['value'],
					'<=' => $left <= $p['value'],
					default => true,
				};
			},
			'filter_var' => fn($v, $p) => filter_var($v, $p['filter'], $p['flags'] ?? 0) !== false,
			'number' => is_numeric(...),
		],
		'abbr' => [
			'integer' => 'int',
			'unsigned' => 'uint',
			'string' => 'str',
			'boolean' => 'bool',
			'arr' => 'array',
			'values' => 'array',
			'body' => ['from' => 'body'],
			'query' => ['from' => 'query'],
			'header' => ['from' => 'header'],
			'cookie' => ['from' => 'cookie'],
			'file' => ['from' => 'file'],
			'params' => ['from' => 'params'],
			'remove' => ['null' => 'remove'],
			'throw' => ['null' => 'throw'],
			'email' => ['filter_var' => ['filter' => FILTER_VALIDATE_EMAIL]],
			'url' => ['filter_var' => ['filter' => FILTER_VALIDATE_URL]],
			'ip-v4' => ['filter_var' => ['filter' => FILTER_VALIDATE_IP, 'flags' => FILTER_FLAG_IPV4]],
			'ip-v6' => ['filter_var' => ['filter' => FILTER_VALIDATE_IP, 'flags' => FILTER_FLAG_IPV6]],
		],
		'parse' => [
			'/^(>=?|<=?|!=|=)(\d+(?:\.\d+)?)$/' => fn($m) => ['cmp' => ['op' => $m[1], 'value' => (float)$m[2]]],
		],
	],
	'^#out' => ['type' => 'json', 'emit' => container('#mode:cli') ? 'cli' : 'http'],
]);
hook('after', output(...));
register_shutdown_function(hook(...));
