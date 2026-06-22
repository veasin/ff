<?php
declare(strict_types=1);
namespace nx;
/**
 * HTTP 请求函数，一函数多用。支持 encode/decode 控制请求体编码和响应解码。
 * 无空格 'url' → 默认 GET；'METHOD url' → 指定方法。
 * ```
 * http('GET https://api.example.com/users');
 * http('https://api.example.com/users');
 * http('POST https://...', ['name' => 'John']);
 * http('PUT https://.../1', ['name' => 'John']);
 * http('DELETE https://.../1');
 * http('GET https://...', query: ['page' => 1], headers: ['Authorization: Bearer xxx']);
 * http('POST https://...', ['name' => 'John'], option: ['timeout' => 10]);
 * http('POST https://...', option: ['body' => [...], 'query' => [...], 'headers' => [...]]);
 * http('POST https://...', ['a'=>1], option: ['encode'=>'form', 'decode'=>'json']);  // form 发送，json 解码
 * http('POST https://...', ['x'=>1], option: ['encode'=>fn($b,$c)=>custom($b)]);      // 自定义编码
 * $body = http('GET https://...', mode: 'body');
 * $code = http('GET https://...', mode: 'code');
 * $ok   = http('GET https://...', mode: 'ok');
 * ```
 * encode 支持: null(CT自动)|'json'|'form'|Closure fn(mixed, 'encode'):?string
 * decode 支持: null(CT自动)|'json'|'form'|'raw'|Closure fn(string, 'decode'):mixed, 未设置时同 encode
 * @param string      $request 'METHOD url'，无空格默认 GET
 * @param mixed       $body    请求体，array 按 CT 编码，string 原样，null 无
 * @param array|null  $query   URL 查询参数
 * @param array|null  $headers 请求头，支持关联 ['K'=>'v'] 和扁平 ['K: v']
 * @param array|null  $option  扩展: body/query/headers 覆盖, timeout, ssl_verify, redirect, log, encode, decode
 * @param string|null $mode    返回模式: null=完整, 'body'|'code'|'ok'|'headers'
 * @return mixed 失败 null；完整返回 ['body','code','headers','message']
 */
function http(string $request,
	mixed $body = null,
	?array $query = null,
	?array $headers = null,
	?array $option = null,
	?string $mode = null,
): mixed{
	[$method, $url] = str_contains($request, ' ') ? explode(' ', $request, 2) : ['get', $request];
	$option ??= [];
	$body = $option['body'] ?? $body;
	if(isset($option['query'])) $query = array_merge($query ?? [], $option['query']);
	if(isset($option['headers'])) $headers = array_merge($headers ?? [], $option['headers']);
	if($query) $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
	$flatHeaders = [];
	if($headers) foreach($headers as $k => $v) $flatHeaders[] = is_string($k) ? "$k: $v" : (string)$v;
	$encode = $option['encode'] ?? null;
	$encodedBody = null;
	if($encode instanceof \Closure) $encodedBody = $encode($body, 'encode');
	elseif(is_iterable($body)){
		if(!is_array($body)) $body = iterator_to_array($body);
		$ct = null;
		foreach($flatHeaders as $h){
			if(stripos($h, 'content-type:') === 0){
				$ct = strtolower(trim(explode(':', $h, 2)[1]));
				break;
			}
		}
		$isForm = match (true) {
			$encode === 'form' => true,
			$encode === null && $ct !== null => str_contains($ct, 'urlencoded'),
			default => false,
		};
		$encodedBody = $isForm ? http_build_query($body) : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if(!$ct) $flatHeaders[] = $isForm ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
	}
	elseif($body !== null) $encodedBody = (string)$body;
	$config = array_merge(container('#http') ?? [], $option);
	$config['headers'] = $flatHeaders;
	if($config['log'] ?? false) log("http $method $url");
	$driver = container('#http.driver');
	$result = $driver !== null ? $driver($method, $url, $encodedBody, $flatHeaders, $config)
		: (extension_loaded('curl') ? http\curl($method, $url, $encodedBody, $flatHeaders, $config)
		: http\stream($method, $url, $encodedBody, $flatHeaders, $config));
	if($result === null) return null;
	$parsed = [];
	foreach($result['headers'] as $line){
		$line = trim($line);
		if(str_contains($line, ':')){
			[$name, $value] = explode(':', $line, 2);
			$name = strtolower(trim($name));
			$value = trim($value);
			$parsed[$name] = isset($parsed[$name]) ? [...(array)$parsed[$name], $value] : $value;
		}
	}
	$result['headers'] = $parsed;
	if(is_string($result['body'])){
		$decode = $option['decode'] ?? $encode ?? null;
		$ct = $result['headers']['content-type'] ?? '';
		if(is_array($ct)) $ct = end($ct);
		if($decode instanceof \Closure) $result['body'] = $decode($result['body'], 'decode');
		elseif($decode === 'raw'){
			/* keep raw */
		}
		elseif($decode === 'json'){
			$d = json_decode($result['body'], true);
			if(json_last_error() === JSON_ERROR_NONE) $result['body'] = $d;
		}
		elseif($decode === 'form'){
			parse_str($result['body'], $result['body']);
		}
		elseif($decode === null){
			$parser = container("#in.content.$ct") ?? [];
			if($parser) $result['body'] = $parser($result['body'], 'decode');
			elseif(str_contains($ct, 'json')){
				$d = json_decode($result['body'], true);
				if(json_last_error() === JSON_ERROR_NONE) $result['body'] = $d;
			}
			elseif(str_contains($ct, 'urlencoded')){
				parse_str($result['body'], $result['body']);
			}
		}
	}
	return match ($mode) {
		'body' => $result['body'],
		'code' => $result['code'],
		'ok' => $result['code'] >= 200 && $result['code'] < 300,
		'headers' => $result['headers'],
		default => $result,
	};
}
