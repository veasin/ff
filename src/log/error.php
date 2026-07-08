<?php
declare(strict_types=1);
namespace ff\log;

function error(string $level, string|array|object $message, array $context = []): void{
	$msg = is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE);
	if($context) $msg = strtr($msg, array_combine(
		array_map(fn($k) => '{'.$k.'}', array_keys($context)),
		array_map(fn($v) => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE), array_values($context))
	));
	error_log("[$level] $msg");
}
