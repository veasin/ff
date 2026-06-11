<?php
namespace nx\middleware\prefab;

use function nx\{container, from, output};

/**
 * 静态文件服务中间件。根据 URI 在指定目录查找静态文件，自动设置 MIME 类型，支持多种缓存策略。目录自动追加 index.html。扩展 MIME 类型通过 container('#static:mimes') 配置。
 * 使用方式:
 * ```
 * middleware(serve('/var/www/public'), $handler);//基础使用（无缓存头）
 * middleware(serve('/var/www/public', false), $handler);//强制不缓存
 * middleware(serve('/var/www/public', 31536000), $handler);//自定义 max-age
 * middleware(serve('/var/www/public', 'etag'), $handler);//ETag 条件缓存
 * middleware(serve('/var/www/public', 'modified'), $handler);//Last-Modified 条件缓存
 * middleware(serve('/var/www/public', ['control' => 'etag,modified', 'age' => 86400]), $handler);//组合策略
 * ```
 * @param string                      $root  静态文件根目录
 * @param null|false|int|string|array $cache 缓存策略
 * @return callable 中间件函数
 */
function serve(string $root, null|false|int|string|array $cache = null): callable{
	return function($next) use ($root, $cache){
		$uri = parse_url(from('uri', 'input') ?? '/', PHP_URL_PATH);
		$ext = pathinfo($uri, PATHINFO_EXTENSION);
		$file = $root . $uri;
		if(is_dir($file)) $file .= '/index.html';
		if(!file_exists($file) || !is_file($file)) return $next();
		static $types = [
			'html' => 'text/html',
			'htm' => 'text/html',
			'txt' => 'text/plain',
			'css' => 'text/css',
			'js' => 'application/javascript',
			'json' => 'application/json',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'svg' => 'image/svg+xml',
			'ico' => 'image/x-icon',
			'woff' => 'font/woff',
			'woff2' => 'font/woff2',
			'ttf' => 'font/ttf',
			'zip' => 'application/zip',
		];
		$contentType = container("#static:mimes.$ext") ?? $types[$ext] ?? (mime_content_type($file) || 'application/octet-stream');
		$cfg = $cache ?? container('#static:cache');
		[$control, $age] = match (true) {
			false === $cfg => [false, 0],
			is_int($cfg) => [null, $cfg],
			is_string($cfg) => [$cfg, 0],
			is_array($cfg) => [$cfg['control'] ?? '', $cfg['age'] ?? 0],
			default => ['', 0],
		};
		$headers = ['Content-Type' => $contentType];
		if(false === $control) $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
		else{
			if($control){
				$mtime = filemtime($file);
				if(str_contains($control, 'etag')){
					$tag = '"' . $mtime . '-' . filesize($file) . '"';
					if(from('if-none-match', 'header') === $tag) return output(null, ['code' => 304, 'type' => 'http']);
					$headers['ETag'] = $tag;
				}
				if(str_contains($control, 'modified')){
					$lm = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
					if(from('if-modified-since', 'header') === $lm) return output(null, ['code' => 304, 'type' => 'http']);
					$headers['Last-Modified'] = $lm;
				}
			}
			if($age) $headers['Cache-Control'] = "public, max-age=$age";
		}
		$content = file_get_contents($file);
		$headers['Content-Length'] = strlen($content);
		output($content, ['type' => 'http', 'headers' => $headers]);
		return $content;
	};
}
