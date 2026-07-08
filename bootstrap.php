<?php
// bootstrap.php — ff core 自注册（via ext 系统）
use function ff\container;

container('^#ext', [
	'http' => [
		'curl'   => \ff\http\curl(...),
		'stream' => \ff\http\stream(...),
	],
	'queue' => [
		'db' => \ff\queue\db(...),
	],
	'output' => [
		'json' => \ff\output\type\json(...),
		'view' => \ff\output\type\view(...),
		'file' => \ff\output\type\file(...),
		'http' => \ff\output\http(...),
	],
]);
