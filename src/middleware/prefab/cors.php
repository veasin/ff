<?php
namespace ff\middleware\prefab;

use function ff\{from, output};

/**
 * CORS 跨域请求中间件
 * 使用方式:
 * ```
 * middleware(cors(), $handler);//基础使用
 * middleware(cors(['origin' => 'https://example.com', 'methods' => 'GET,POST']), $handler);//自定义配置
 * ```
 * 配置选项:
 * - origin: 允许的源，默认 '*'
 * - methods: 允许的方法，默认 'GET,POST,PUT,DELETE,OPTIONS'
 * - headers: 允许的请求头，默认 'Content-Type,Authorization,X-CSRF-Token'
 * - credentials: 是否允许凭证，默认 false
 * - max-age: 预检请求缓存时间，默认 86400 秒
 * @param array $options 配置选项
 * @return callable 中间件函数
 */
function cors(array $options = []): callable{
	$opts = [
		'origin' => '*',
		'methods' => 'GET,POST,PUT,DELETE,OPTIONS',
		'headers' => 'Content-Type,Authorization,X-CSRF-Token',
		'credentials' => false,
		'max-age' => 86400,
		...$options,
	];
	return function($next) use ($opts){
		$origin = is_array($opts['origin']) ? ($opts['origin'][array_rand($opts['origin'])] ?? '*') : $opts['origin'];
		output(null, [
			'code' => 200,
			'headers' => [
				'Access-Control-Allow-Origin' => $origin,
				'Access-Control-Allow-Methods' => $opts['methods'],
				'Access-Control-Allow-Headers' => $opts['headers'],
				'Access-Control-Allow-Credentials' => $opts['credentials'] ? 'true' : 'false',
				'Access-Control-Max-Age' => $opts['max-age'],
			],
		]);
		return from('method', 'input') === 'OPTIONS' ? ['ok' => true] : $next();
	};
}
