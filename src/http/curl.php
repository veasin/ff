<?php
declare(strict_types=1);
namespace nx\http;
/**
 * cURL 驱动，传输层纯函数。被 http() 自动调用。
 * @internal
 */
function curl(string $method, string $url, ?string $body, array $headers, array $config): ?array{
	$method = strtoupper($method);
	$config = array_merge(\nx\container('#http.curl') ?? [], $config);
	$ch = curl_init();
	if($ch === false) return null;
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => ($config['redirect'] ?? 5) > 0,
		CURLOPT_MAXREDIRS => $config['redirect'] ?? 5,
		CURLOPT_SSL_VERIFYPEER => $config['ssl_verify'] ?? false,
		CURLOPT_SSL_VERIFYHOST => ($config['ssl_verify'] ?? false) ? 2 : 0,
		CURLOPT_TIMEOUT => $config['timeout'] ?? 30,
		CURLOPT_HTTPHEADER => $headers,
	]);
	if($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	$rawHeaders = [];
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function($curl, $line) use (&$rawHeaders){
		$rawHeaders[] = $line;
		return strlen($line);
	});
	$raw = curl_exec($ch);
	if($raw === false){ curl_close($ch); return null; }
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return ['body' => $raw, 'code' => $code, 'headers' => $rawHeaders, 'message' => ''];
}
