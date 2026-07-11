<?php
// bootstrap.php — ff core 自注册（via ext 系统）
use function ff\{container, hook, output};

container('^#ext', [
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
]);
container('^#out', ['type' => 'json', 'emit' => container('#mode:cli') ? 'cli' : 'http']);
hook('after', output(...));
register_shutdown_function(hook(...));
