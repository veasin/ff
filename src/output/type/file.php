<?php
declare(strict_types=1);
namespace nx\output\type;
/**
 * @param array $response
 * @return array
 * @internal
 */
function file(array $response): array{
	$path = $response['file'] ?? null;
	if(!$path || !file_exists($path)){
		$response['code'] = 404;
		$response['body'] = '';
		return $response;
	}
	$download = $response['body'] ?? false;
	$response['body'] = file_get_contents($path);
	$response['headers'] = [...($response['headers'] ?? [])];
	$mime = mime_content_type($path);
	if($mime) $response['headers']['Content-Type'] = $mime;
	if($download){
		$filename = basename($path);
		$response['headers']['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
		$response['headers']['Content-Length'] = filesize($path);
	}
	return $response;
}
