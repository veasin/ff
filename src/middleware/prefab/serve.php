<?php
namespace nx\middleware\prefab;
use function nx\{from, output, container};

/**
 * 静态文件服务中间件
 *
 * 使用方式:
 * - 基础使用: middleware(serve('/var/www/public'), $handler)
 * - 自定义映射: middleware(serve('/var/www/public', ['html' => 'index.php']), $handler)
 *
 * 行为:
 * - 根据 URI 查找对应文件
 * - 自动识别常见文件类型并设置正确的 Content-Type
 * - 目录自动追加 index.html
 * - 启用一年缓存期
 *
 * 扩展 MIME 类型:
 * - container('#static:mimes', ['自定义扩展名' => 'application/xxx'])
 *
 * @param string $root 静态文件根目录
 * @param array  $map  扩展名到文件的映射
 * @return callable 中间件函数
 */
function serve(string $root, array $map = []): callable{
	return function($next) use ($root, $map){
		$uri = parse_url(from('uri', 'input') ?? '/', PHP_URL_PATH);
		$ext = pathinfo($uri, PATHINFO_EXTENSION);
		$file = $map[$ext] ?? null;
		if(!$file){
			$file = $root . $uri;
			if(is_dir($file)) $file .= '/index.html';
		}
		if(!file_exists($file) || !is_file($file)) return $next();
		$types = [
			'html' => 'text/html',
			'htm' => 'text/html',
			'txt' => 'text/plain',
			'css' => 'text/css',
			'js' => 'application/javascript',
			'json' => 'application/json',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'svg' => 'image/svg+xml',
			'ico' => 'image/x-icon',
			'woff' => 'font/woff',
			'woff2' => 'font/woff2',
			'ttf' => 'font/ttf',
			'zip' => 'application/zip',
			'xml' => 'application/xml',
			...(container('#static:mimes') ?? []),
		];
		$contentType = $types[$ext] ?? (mime_content_type($file) || 'application/octet-stream');
		$content = file_get_contents($file);
		output($content, 'http', [
			'headers' => [
				'Content-Type' => $contentType,
				'Content-Length' => strlen($content),
				'Cache-Control' => 'public, max-age=31536000',
			],
		]);
		return $content;
	};
}
