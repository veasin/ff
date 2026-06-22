<?php
declare(strict_types=1);
namespace nx\http;

use function nx\container;

/**
 * PHP stream 驱动，传输层纯函数。被 http() 自动调用。
 * @internal
 */
function stream(string $method, string $url, ?string $body, array $headers, array $config): ?array{
	$config = array_merge(container('#http.stream') ?? [], $config);
	$http = [
		'method' => strtoupper($method),
		'header' => implode("\r\n", $headers),
		'timeout' => $config['timeout'] ?? 30,
		'follow_location' => ($config['redirect'] ?? 5) > 0,
		'max_redirects' => $config['redirect'] ?? 5,
		'ignore_errors' => true,
		'protocol_version' => 1.1,
	];
	if(isset($config['ssl_verify'])) $http['ssl'] = ['verify_peer' => $config['ssl_verify']];
	if($body !== null) $http['content'] = $body;
	$ctx = stream_context_create(['http' => $http]);
	$raw = @file_get_contents($url, false, $ctx);
	if($raw === false) return null;
	preg_match('#HTTP/\d\.\d\s+(\d+)\s+(.+)#', $http_response_header[0] ?? '', $m);
	$code = (int)($m[1] ?? 0);
	return ['body' => $raw, 'code' => $code, 'headers' => $http_response_header ?? [], 'message' => $m[2] ?? ''];
}
